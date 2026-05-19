<?php

namespace App\Services;

use App\Events\GameEvent;
use App\Events\RoomListEvent;
use App\Models\ChatMessage;
use App\Models\GameHistory;
use App\Models\GameStat;
use App\Models\Hint;
use App\Models\Player;
use App\Models\Room;
use App\Models\Round;
use App\Models\Vote;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GameService
{
    public function __construct(
        private AiWordService $aiWordService,
        private RoomCleanupService $cleanupService,
        private CreditService $creditService,
    ) {}

    private function cleanAllStale(): void
    {
        $this->cleanupService->purgeStalePlayersFromAllRooms(broadcastGameEvents: false);
    }

    private function purgeStalePlayers(Room $room): void
    {
        $this->cleanupService->purgeStalePlayers($room, broadcastGameEvents: true);
    }

    /**
     * Create a new game room and add the creator as the first player.
     *
     * @return array Data for broadcasting to frontend.
     */
    public function createRoom(string $nickname, string $type, int $maxPlayers, int $roundsPerGame, string $language = 'en', ?array $avatar = null, ?int $userId = null, ?string $category = null, string $difficulty = 'medium'): array
    {
        return DB::transaction(function () use ($nickname, $type, $maxPlayers, $roundsPerGame, $language, $avatar, $userId, $category, $difficulty) {
            $room = Room::create([
                'code' => Room::generateCode(),
                'type' => $type,
                'status' => 'waiting',
                'max_players' => $maxPlayers,
                'rounds_per_game' => $roundsPerGame,
                'language' => in_array($language, ['en', 'ar']) ? $language : 'en',
                'category' => $category,
                'difficulty' => in_array($difficulty, ['easy', 'medium', 'hard']) ? $difficulty : 'medium',
                'current_round' => 0,
            ]);

            $player = Player::create([
                'nickname' => $nickname,
                'room_id' => $room->id,
                'user_id' => $userId,
                'is_ready' => false,
                'is_imposter' => false,
                'score' => 0,
                'avatar' => $avatar,
            ]);

            $room->update(['creator_id' => $player->id]);

            broadcast(new GameEvent($room->id, 'room_created', [
                'room' => $this->formatRoom($room->fresh()),
                'player' => $this->formatPlayer($player),
            ]));

            if ($room->type === 'public') {
                broadcast(new RoomListEvent('created', $this->formatRoom($room->fresh())));
            }

            return [
                'room' => $this->formatRoom($room->fresh()),
                'player' => $this->formatPlayer($player),
            ];
        });
    }

    /**
     * Join an existing room by code.
     * If the game is in progress, the player joins as a spectator.
     *
     * @return array Data for broadcasting to frontend.
     *
     * @throws \Exception
     */
    public function joinRoom(string $code, string $nickname, ?array $avatar = null, ?int $userId = null): array
    {
        $room = Room::where('code', strtoupper($code))->first();

        if (! $room) {
            throw new \Exception(__('errors.room_not_found'));
        }

        $this->purgeStalePlayers($room);

        $room = $room->fresh();
        if (! $room) {
            throw new \Exception(__('errors.room_gone'));
        }

        $isSpectator = ! in_array($room->status, ['waiting', 'finished']);

        if (! $isSpectator) {
            // Normal join (waiting room or finished game)
            if ($room->status === 'waiting' && $room->players()->count() >= $room->max_players) {
                throw new \Exception(__('errors.room_full'));
            }

            if ($room->players()->where('nickname', $nickname)->exists()) {
                throw new \Exception(__('errors.nick_taken'));
            }
        } else {
            // Spectator join: allow a generous spectator cap (2x max_players)
            $spectatorCount = $room->players()->where('is_spectator', true)->count();
            if ($spectatorCount >= max(5, $room->max_players)) {
                throw new \Exception(__('errors.room_full'));
            }

            // Allow duplicate nicknames for spectators by appending a suffix if needed
            if ($room->players()->where('nickname', $nickname)->exists()) {
                $nickname = $nickname.' ('.($spectatorCount + 1).')';
            }
        }

        $player = Player::create([
            'nickname' => $nickname,
            'room_id' => $room->id,
            'user_id' => $userId,
            'is_ready' => $isSpectator ? true : false,
            'is_imposter' => false,
            'is_spectator' => $isSpectator,
            'score' => 0,
            'avatar' => $avatar,
        ]);

        $room->touchActivity();

        if ($isSpectator) {
            broadcast(new GameEvent($room->id, 'spectator_joined', [
                'room' => $this->formatRoom($room->fresh()),
                'player' => $this->formatPlayer($player),
            ]));
        } else {
            broadcast(new GameEvent($room->id, 'player_joined', [
                'room' => $this->formatRoom($room->fresh()),
                'player' => $this->formatPlayer($player),
            ]));

            if ($room->type === 'public') {
                broadcast(new RoomListEvent('updated', $this->formatRoom($room->fresh())));
            }
        }

        return [
            'room' => $this->formatRoom($room->fresh()),
            'player' => $this->formatPlayer($player),
        ];
    }

    /**
     * Toggle a player's ready state. If all players are ready, signal that the game can start.
     *
     * @return array Data for broadcasting to frontend.
     */
    public function toggleReady(int $playerId): array
    {
        $player = Player::findOrFail($playerId);
        $room = $player->room;

        $player->update(['is_ready' => ! $player->is_ready]);

        $room->touchActivity();
        $room = $room->fresh();
        $allReady = $room->players()->count() >= 3 && $room->players()->where('is_ready', false)->doesntExist();

        broadcast(new GameEvent($room->id, 'player_ready', [
            'room' => $this->formatRoom($room),
            'player' => $this->formatPlayer($player->fresh()),
            'all_ready' => $allReady,
        ]));

        return [
            'room' => $this->formatRoom($room),
            'player' => $this->formatPlayer($player->fresh()),
            'all_ready' => $allReady,
        ];
    }

    /**
     * Kick a player from the room. Only the creator can kick.
     */
    public function kickPlayer(int $roomId, int $creatorId, int $targetPlayerId): array
    {
        $room = Room::findOrFail($roomId);

        if ($room->creator_id !== $creatorId) {
            throw new \Exception(__('errors.creator_only'));
        }

        if ($creatorId === $targetPlayerId) {
            throw new \Exception(__('errors.cannot_kick_self'));
        }

        $target = Player::where('id', $targetPlayerId)->where('room_id', $roomId)->first();
        if (! $target) {
            throw new \Exception(__('errors.player_not_in_room'));
        }

        return $this->leaveRoom($targetPlayerId);
    }

    /**
     * Remove a player from a room. Transfer creator role if needed.
     * Delete the room if no players remain.
     * Handle mid-game departures (imposter fled, game abort, hint/vote adjustments).
     */
    public function leaveRoom(int $playerId): array
    {
        return DB::transaction(function () use ($playerId) {
            $player = Player::findOrFail($playerId);
            $room = $player->room;

            $wasCreator = $player->id === $room->creator_id;
            $wasImposter = $player->is_imposter;
            $playerNickname = $player->nickname;
            $roomCode = $room->code;
            $roomType = $room->type;
            $roomId = $room->id;
            $isMidGame = in_array($room->status, ['playing', 'voting', 'round_result']);

            // Capture current round info before deleting player
            $currentRound = null;
            if ($isMidGame && $room->current_round > 0) {
                $currentRound = $room->rounds()
                    ->where('round_number', $room->current_round)
                    ->first();
            }

            // Remove the player's hints and votes for the current round
            if ($currentRound) {
                Hint::where('round_id', $currentRound->id)
                    ->where('player_id', $playerId)
                    ->delete();
                Vote::where('round_id', $currentRound->id)
                    ->where('voter_id', $playerId)
                    ->delete();
            }

            $player->delete();

            $remainingPlayers = $room->fresh()->players()->orderBy('id')->get();
            $remainingCount = $remainingPlayers->count();

            // --- No players left: delete room ---
            if ($remainingCount === 0) {
                $room->delete();

                if ($roomType === 'public') {
                    broadcast(new RoomListEvent('removed', ['id' => $roomId, 'code' => $roomCode]));
                }

                broadcast(new GameEvent($roomId, 'room_deleted', [
                    'code' => $roomCode,
                ]));

                return ['deleted' => true, 'code' => $roomCode];
            }

            // --- Transfer creator role if needed ---
            if ($wasCreator) {
                $newCreator = $remainingPlayers->first();
                $room->update(['creator_id' => $newCreator->id]);

                broadcast(new GameEvent($roomId, 'creator_changed', [
                    'room' => $this->formatRoom($room->fresh()),
                    'new_creator_id' => $newCreator->id,
                ]));
            }

            // --- Mid-game handling ---
            if ($isMidGame) {
                // Not enough players to continue: abort the game
                if ($remainingCount < 3) {
                    return $this->abortGame($room, $roomCode, $roomType, $roomId, $playerId, $playerNickname);
                }

                // Imposter left: resolve round as "imposter fled"
                if ($wasImposter) {
                    return $this->handleImposterFled(
                        $room, $currentRound, $playerId, $playerNickname,
                        $roomCode, $roomType, $roomId, $remainingPlayers
                    );
                }

                // Regular crew player left mid-game: adjust game state
                if ($room->status === 'playing' && $currentRound) {
                    $this->adjustHintOrderForDeparture($room, $currentRound, $playerId);
                }

                if ($room->status === 'voting' && $currentRound) {
                    $this->checkAndResolveVotesIfComplete($room, $currentRound);
                }
            }

            $room->touchActivity();

            broadcast(new GameEvent($roomId, 'player_left', [
                'room' => $this->formatRoom($room->fresh()),
                'player_id' => $playerId,
            ]));

            if ($roomType === 'public') {
                broadcast(new RoomListEvent('updated', $this->formatRoom($room->fresh())));
            }

            return ['deleted' => false, 'code' => $roomCode];
        });
    }

    /**
     * Abort the game entirely because not enough players remain.
     */
    private function abortGame(Room $room, string $roomCode, string $roomType, int $roomId, int $playerId, string $playerNickname): array
    {
        $room->update(['status' => 'finished']);

        broadcast(new GameEvent($roomId, 'game_aborted', [
            'room' => $this->formatRoom($room->fresh()),
            'reason' => 'not_enough_players',
            'left_player_id' => $playerId,
            'left_player_nickname' => $playerNickname,
        ]));

        broadcast(new GameEvent($roomId, 'player_left', [
            'room' => $this->formatRoom($room->fresh()),
            'player_id' => $playerId,
        ]));

        if ($roomType === 'public') {
            broadcast(new RoomListEvent('removed', ['id' => $roomId, 'code' => $roomCode]));
        }

        return ['deleted' => false, 'code' => $roomCode, 'game_aborted' => true];
    }

    /**
     * Handle the case where the imposter leaves mid-game.
     * Resolve the current round as "imposter fled" (crew wins).
     * Advance to next round or end game.
     */
    private function handleImposterFled(
        Room $room,
        ?Round $currentRound,
        int $playerId,
        string $playerNickname,
        string $roomCode,
        string $roomType,
        int $roomId,
        $remainingPlayers
    ): array {
        // Score: all remaining crew players get 2 points (imposter fled)
        foreach ($remainingPlayers as $p) {
            $p->increment('score', 2);
        }

        // Save round results if there is an active round
        if ($currentRound) {
            $roundResults = [
                'round_number' => $currentRound->round_number,
                'real_word' => $currentRound->real_word,
                'imposter_hint' => $currentRound->imposter_hint,
                'imposter' => [
                    'id' => $playerId,
                    'nickname' => $playerNickname,
                    'is_imposter' => true,
                    'avatar' => $player->avatar,
                ],
                'vote_tally' => [],
                'imposter_caught' => false,
                'is_tie' => false,
                'winner' => 'crew',
                'imposter_fled' => true,
            ];

            $currentRound->update([
                'winner' => 'crew',
                'imposter_caught' => false,
                'vote_tally' => [],
            ]);
        }

        // Check if game is over
        $isGameOver = $currentRound
            ? $currentRound->round_number >= $room->rounds_per_game
            : true;

        if ($isGameOver) {
            $room->update(['status' => 'finished']);

            $players = $room->players()->orderByDesc('score')->get();

            // Credit rewards: crew wins when imposter flees
            foreach ($remainingPlayers as $p) {
                if (! $p->user_id) {
                    continue;
                }
                try {
                    $user = $p->user;
                    $this->creditService->rewardGameEvent($user, 'game_played');
                    $this->creditService->rewardGameEvent($user, 'win_as_crew');
                } catch (\Throwable $e) {
                    Log::warning('Credit reward failed', [
                        'player_id' => $p->id,
                        'user_id' => $p->user_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Track game stats and history
            $this->trackGameStats($room, 'crew');

            broadcast(new GameEvent($roomId, 'imposter_fled', [
                'room' => $this->formatRoom($room->fresh()),
                'round_results' => $roundResults ?? null,
                'final_scores' => $this->formatPlayers($players),
                'is_game_over' => true,
                'fled_player' => ['id' => $playerId, 'nickname' => $playerNickname],
            ]));
        } else {
            $room->update(['status' => 'round_result']);

            broadcast(new GameEvent($roomId, 'imposter_fled', [
                'room' => $this->formatRoom($room->fresh()),
                'round_results' => $roundResults ?? null,
                'is_game_over' => false,
                'fled_player' => ['id' => $playerId, 'nickname' => $playerNickname],
            ]));
        }

        broadcast(new GameEvent($roomId, 'player_left', [
            'room' => $this->formatRoom($room->fresh()),
            'player_id' => $playerId,
        ]));

        if ($roomType === 'public') {
            broadcast(new RoomListEvent('updated', $this->formatRoom($room->fresh())));
        }

        return ['deleted' => false, 'code' => $roomCode, 'imposter_fled' => true];
    }

    /**
     * Adjust hint order and state when a crew player leaves during the hint phase.
     */
    private function adjustHintOrderForDeparture(Room $room, Round $currentRound, int $playerId): void
    {
        $hintOrder = $currentRound->hint_order ?? [];

        // Remove the departed player from the hint order
        $newHintOrder = array_values(array_filter($hintOrder, fn ($id) => $id !== $playerId));

        // Check how many hints are already submitted in the current cycle
        $currentCycle = $currentRound->hint_cycle ?? 1;
        $submittedCount = $currentRound->hints()->where('hint_cycle', $currentCycle)->count();
        $totalNeeded = count($newHintOrder);

        $allHintsSubmitted = $totalNeeded > 0 && $submittedCount >= $totalNeeded;

        $nextPlayerId = null;
        if (! $allHintsSubmitted && count($newHintOrder) > 0) {
            // Find the first player in order who hasn't submitted in this cycle
            $submittedPlayerIds = $currentRound->hints()
                ->where('hint_cycle', $currentCycle)
                ->pluck('player_id')
                ->toArray();

            foreach ($newHintOrder as $pid) {
                if (! in_array($pid, $submittedPlayerIds)) {
                    $nextPlayerId = $pid;
                    break;
                }
            }
        }

        $currentRound->update(['hint_order' => $newHintOrder]);

        if ($allHintsSubmitted) {
            broadcast(new GameEvent($room->id, 'hints_complete', [
                'room' => $this->formatRoom($room->fresh()),
                'round' => $this->formatRound($currentRound->fresh()),
                'hints' => $this->formatHintsForCycle($currentRound->fresh(), $currentCycle),
                'previous_hints_by_cycle' => $this->formatHintsByCycle($currentRound->fresh()),
                'hint_cycle' => $currentCycle,
                'creator_id' => $room->creator_id,
            ]));
        } else {
            broadcast(new GameEvent($room->id, 'hint_order_updated', [
                'room' => $this->formatRoom($room->fresh()),
                'hint_order' => $newHintOrder,
                'current_turn_player_id' => $nextPlayerId,
                'hints' => $this->formatHintsForCycle($currentRound->fresh(), $currentCycle),
                'hint_cycle' => $currentCycle,
            ]));
        }
    }

    /**
     * Check if all remaining players have voted; resolve immediately if so.
     */
    private function checkAndResolveVotesIfComplete(Room $room, Round $currentRound): void
    {
        $remainingCount = $room->players()->count();
        $voteCount = $currentRound->votes()->count();

        if ($voteCount >= $remainingCount) {
            $this->resolveVotes($room->id);
        }
    }

    /**
     * Start the game: generate a word, assign the imposter, create the first round.
     *
     * @return array Data for broadcasting to frontend.
     *
     * @throws \Exception
     */
    public function startGame(int $roomId): array
    {
        return DB::transaction(function () use ($roomId) {
            $room = Room::with('players')->findOrFail($roomId);

            if ($room->status !== 'waiting') {
                throw new \Exception(__('errors.game_started'));
            }

            $players = $room->players;
            if ($players->count() < 3) {
                throw new \Exception(__('errors.min_players'));
            }

            // Convert any spectators to regular players
            $room->players()->where('is_spectator', true)->update([
                'is_spectator' => false,
                'is_ready' => true,
            ]);

            // Reset all players' imposter status from any prior rounds
            $room->players()->update(['is_imposter' => false]);

            // Gather used words from previous rounds in this room
            $usedWords = $room->rounds()->pluck('real_word')->toArray();

            // Generate the full word pool for the entire game in one AI call
            $pool = $this->aiWordService->generateWords(
                $room->rounds_per_game,
                $usedWords,
                $room->language,
                $room->category,
                $room->difficulty ?? 'medium'
            );

            $generated = array_shift($pool);

            // Assign imposter randomly
            $imposter = $players->random();
            $imposter->update(['is_imposter' => true]);

            // Create the first round with a random hint order
            $roundNumber = 1;
            $hintOrder = $players->shuffle()->pluck('id')->toArray();
            $round = Round::create([
                'room_id' => $room->id,
                'round_number' => $roundNumber,
                'real_word' => $generated['word'],
                'imposter_hint' => $generated['hint'],
                'imposter_id' => $imposter->id,
                'hint_order' => $hintOrder,
                'hint_cycle' => 1,
                'turn_started_at' => now(),
            ]);

            $room->update([
                'status' => 'playing',
                'current_round' => $roundNumber,
                'word_pool' => array_values($pool),
            ]);

            $room->touchActivity();

            broadcast(new GameEvent($room->id, 'game_started', [
                'room' => $this->formatRoom($room->fresh()),
                'round' => $this->formatRound($round),
            ]));

            if ($room->type === 'public') {
                broadcast(new RoomListEvent('removed', $this->formatRoom($room->fresh())));
            }

            return [
                'room' => $this->formatRoom($room->fresh()),
                'round' => $this->formatRound($round),
                'imposter_id' => $imposter->id,
                'real_word' => $generated['word'],
                'imposter_hint' => $generated['hint'],
            ];
        });
    }

    /**
     * Submit a hint for a player in a round. Check if all hints are submitted.
     *
     * @return array Data for broadcasting to frontend.
     */
    public function submitHint(int $roundId, int $playerId, string $content): array
    {
        $round = Round::findOrFail($roundId);
        $room = $round->room;

        // Spectators cannot submit hints
        $hintingPlayer = Player::find($playerId);
        if ($hintingPlayer && $hintingPlayer->is_spectator) {
            throw new \Exception(__('errors.not_playing'));
        }

        $currentCycle = $round->hint_cycle ?? 1;

        // Check if hint already submitted in this cycle
        $existing = Hint::where('round_id', $roundId)
            ->where('player_id', $playerId)
            ->where('hint_cycle', $currentCycle)
            ->first();

        if ($existing) {
            throw new \Exception(__('errors.hint_submitted'));
        }

        // Enforce turn order: only the current player in sequence can submit
        // Count only hints from the current cycle for turn order
        $hintOrder = $round->hint_order ?? [];
        $submittedCount = $round->hints()->where('hint_cycle', $currentCycle)->count();

        if (! empty($hintOrder) && isset($hintOrder[$submittedCount])) {
            $expectedPlayerId = $hintOrder[$submittedCount];
            if ($playerId != $expectedPlayerId) {
                $expectedPlayer = Player::find($expectedPlayerId);
                throw new \Exception(__('errors.not_your_turn', ['player' => $expectedPlayer->nickname ?? '']));
            }
        }

        Hint::create([
            'round_id' => $roundId,
            'player_id' => $playerId,
            'hint_cycle' => $currentCycle,
            'content' => trim($content),
        ]);

        $room->touchActivity();
        $player = Player::find($playerId);
        $round->refresh();

        // Count only current-cycle hints to determine if all are in
        $currentCycleHintCount = $round->hints()->where('hint_cycle', $currentCycle)->count();
        $allHintsSubmitted = $currentCycleHintCount >= $room->players()->where('is_spectator', false)->count();

        // Determine the next player in turn order
        $nextPlayerId = null;
        if (! $allHintsSubmitted && ! empty($hintOrder)) {
            $nextIdx = $currentCycleHintCount;
            $nextPlayerId = $hintOrder[$nextIdx] ?? null;
            // Reset timer for the next player's turn
            $round->update(['turn_started_at' => now()]);
        }

        if ($allHintsSubmitted) {
            // Clear any previous phase votes for this decision cycle
            $room->update(['phase_votes' => null]);

            broadcast(new GameEvent($room->id, 'hints_complete', [
                'room' => $this->formatRoom($room->fresh()),
                'round' => $this->formatRound($round->fresh()),
                'hints' => $this->formatHintsForCycle($round->fresh(), $currentCycle),
                'previous_hints_by_cycle' => $this->formatHintsByCycle($round->fresh()),
                'hint_cycle' => $currentCycle,
                'creator_id' => $room->creator_id,
            ]));
        } else {
            broadcast(new GameEvent($room->id, 'hint_submitted', [
                'room' => $this->formatRoom($room->fresh()),
                'player_id' => $playerId,
                'nickname' => $player->nickname,
                'hints' => $this->formatHintsForCycle($round->fresh(), $currentCycle),
                'previous_hints_by_cycle' => $this->formatHintsByCycle($round->fresh()),
                'hints_count' => $currentCycleHintCount,
                'total_players' => $room->players()->where('is_spectator', false)->count(),
                'next_player_id' => $nextPlayerId,
                'hint_order' => $hintOrder,
                'hint_cycle' => $currentCycle,
            ]));
        }

        return [
            'all_hints_submitted' => $allHintsSubmitted,
            'hints_count' => $currentCycleHintCount,
            'next_player_id' => $nextPlayerId,
        ];
    }

    /**
     * Skip a player's hint turn (timer expired). Moves to the next player in order.
     * If all remaining hints are skipped or submitted, triggers hints_complete.
     */
    public function skipHint(int $roundId, int $playerId): array
    {
        $round = Round::findOrFail($roundId);
        $room = $round->room;

        if ($room->status !== 'playing') {
            throw new \Exception(__('errors.not_playing'));
        }

        $currentCycle = $round->hint_cycle ?? 1;

        // Verify it's this player's turn (based on current cycle)
        $hintOrder = $round->hint_order ?? [];
        $submittedCount = $round->hints()->where('hint_cycle', $currentCycle)->count();

        if (empty($hintOrder) || ! isset($hintOrder[$submittedCount])) {
            throw new \Exception(__('errors.no_active_round'));
        }

        $expectedPlayerId = $hintOrder[$submittedCount];
        if ($playerId != $expectedPlayerId) {
            throw new \Exception(__('errors.not_your_turn', ['player' => Player::find($expectedPlayerId)?->nickname ?? '']));
        }

        // Check if the hint timer hasn't expired yet (20 seconds)
        if ($round->turn_started_at && $round->turn_started_at->diffInSeconds(now()) < 20) {
            throw new \Exception(__('errors.timer_not_expired'));
        }

        // Submit a placeholder hint for the skipped player in the current cycle
        Hint::create([
            'round_id' => $roundId,
            'player_id' => $playerId,
            'hint_cycle' => $currentCycle,
            'content' => '...',
        ]);

        $room->touchActivity();
        $round->refresh();

        $currentCycleHintCount = $round->hints()->where('hint_cycle', $currentCycle)->count();
        $allHintsSubmitted = $currentCycleHintCount >= $room->players()->where('is_spectator', false)->count();

        $nextPlayerId = null;
        if (! $allHintsSubmitted && ! empty($hintOrder)) {
            $nextIdx = $currentCycleHintCount;
            $nextPlayerId = $hintOrder[$nextIdx] ?? null;
            $round->update(['turn_started_at' => now()]);
        }

        $player = Player::find($playerId);

        if ($allHintsSubmitted) {
            $room->update(['phase_votes' => null]);
            broadcast(new GameEvent($room->id, 'hints_complete', [
                'room' => $this->formatRoom($room->fresh()),
                'round' => $this->formatRound($round->fresh()),
                'hints' => $this->formatHintsForCycle($round->fresh(), $currentCycle),
                'previous_hints_by_cycle' => $this->formatHintsByCycle($round->fresh()),
                'hint_cycle' => $currentCycle,
                'creator_id' => $room->creator_id,
            ]));
        } else {
            broadcast(new GameEvent($room->id, 'hint_submitted', [
                'room' => $this->formatRoom($room->fresh()),
                'player_id' => $playerId,
                'nickname' => $player?->nickname,
                'hints' => $this->formatHintsForCycle($round->fresh(), $currentCycle),
                'previous_hints_by_cycle' => $this->formatHintsByCycle($round->fresh()),
                'hints_count' => $currentCycleHintCount,
                'total_players' => $room->players()->where('is_spectator', false)->count(),
                'next_player_id' => $nextPlayerId,
                'hint_order' => $hintOrder,
                'hint_cycle' => $currentCycle,
            ]));
        }

        return ['skipped' => true, 'next_player_id' => $nextPlayerId];
    }

    /**
     * Submit a phase vote (continue hints or start voting) after all hints are in.
     * Once all players have voted, the majority decision is executed automatically.
     */
    public function submitPhaseVote(int $roomId, int $playerId, string $choice): array
    {
        if (! in_array($choice, ['vote', 'continue'])) {
            throw new \Exception(__('errors.invalid_phase_vote'));
        }

        $room = Room::with('players')->findOrFail($roomId);

        if ($room->status !== 'playing') {
            throw new \Exception(__('errors.not_playing'));
        }

        $round = $room->rounds()->where('round_number', $room->current_round)->first();

        // Check hints from the current cycle only
        $currentCycle = $round->hint_cycle ?? 1;
        $currentCycleHints = $round->hints()->where('hint_cycle', $currentCycle)->count();

        if (! $round || $currentCycleHints < $room->players()->where('is_spectator', false)->count()) {
            throw new \Exception(__('errors.hints_incomplete'));
        }

        $player = $room->players()->where('id', $playerId)->first();
        if (! $player) {
            throw new \Exception(__('errors.player_not_in_room'));
        }

        $phaseVotes = $room->phase_votes ?? [];
        $phaseVotes[$playerId] = $choice;
        $room->update(['phase_votes' => $phaseVotes]);
        $room->touchActivity();

        $totalPlayers = $room->players()->count();
        $voteCount = count($phaseVotes);
        $voteForVoting = count(array_filter($phaseVotes, fn ($v) => $v === 'vote'));
        $voteForContinue = count(array_filter($phaseVotes, fn ($v) => $v === 'continue'));

        broadcast(new GameEvent($room->id, 'phase_vote_submitted', [
            'room' => $this->formatRoom($room->fresh()),
            'player_id' => $playerId,
            'choice' => $choice,
            'votes_count' => $voteCount,
            'total_players' => $totalPlayers,
            'vote_for_voting' => $voteForVoting,
            'vote_for_continue' => $voteForContinue,
        ]));

        // Check if all players have voted
        if ($voteCount >= $totalPlayers) {
            $room->update(['phase_votes' => null]);

            if ($voteForVoting > $voteForContinue) {
                return $this->startVoting($roomId, $room->creator_id);
            }

            return $this->advanceRound($roomId, $room->creator_id);
        }

        // Check for early majority (unreachable threshold)
        $majority = (int) ceil(($totalPlayers + 1) / 2);
        if ($voteForVoting >= $majority) {
            $room->update(['phase_votes' => null]);

            return $this->startVoting($roomId, $room->creator_id);
        }
        if ($voteForContinue >= $majority) {
            $room->update(['phase_votes' => null]);

            return $this->advanceRound($roomId, $room->creator_id);
        }

        return ['phase_votes' => $phaseVotes, 'vote_count' => $voteCount];
    }

    public function advanceRound(int $roomId, int $creatorId): array
    {
        $room = Room::with('players')->findOrFail($roomId);

        if ($room->creator_id !== $creatorId) {
            throw new \Exception(__('errors.creator_only'));
        }

        if ($room->status !== 'playing') {
            throw new \Exception(__('errors.not_playing'));
        }

        $round = $room->rounds()->where('round_number', $room->current_round)->first();

        // Count hints from the current cycle only
        $currentCycle = $round->hint_cycle ?? 1;
        $currentCycleHints = $round->hints()->where('hint_cycle', $currentCycle)->count();

        if (! $round || $currentCycleHints < $room->players()->where('is_spectator', false)->count()) {
            throw new \Exception(__('errors.hints_incomplete'));
        }

        // Same round, same imposter — increment hint cycle (keep old hints)
        $newCycle = $currentCycle + 1;
        $round->update([
            'hint_cycle' => $newCycle,
            'turn_started_at' => now(),
        ]);
        $room->touchActivity();

        $hintOrder = $round->hint_order;

        // Gather all previous-cycle hints grouped by cycle
        $allHintsByCycle = $this->formatHintsByCycle($round->fresh());

        broadcast(new GameEvent($room->id, 'round_complete', [
            'room' => $this->formatRoom($room->fresh()),
            'round' => $this->formatRound($round->fresh()),
            'current_round' => $this->formatRound($round->fresh()),
            'hints' => [],
            'previous_hints_by_cycle' => $allHintsByCycle,
            'hint_cycle' => $newCycle,
            'word' => $round->real_word,
            'hint_for_imposter' => $round->imposter_hint,
            'current_turn_player_id' => $hintOrder[0] ?? null,
            'hint_order' => $hintOrder,
            'players' => $this->formatPlayers($room->fresh()->players),
        ]));

        return ['round' => $this->formatRound($round->fresh())];
    }

    public function startVoting(int $roomId, int $creatorId): array
    {
        $room = Room::findOrFail($roomId);

        if ($room->creator_id !== $creatorId) {
            throw new \Exception(__('errors.creator_only'));
        }

        if ($room->status !== 'playing') {
            throw new \Exception(__('errors.not_playing'));
        }

        $room->update(['status' => 'voting']);
        $room->touchActivity();

        $round = $room->rounds()->where('round_number', $room->current_round)->first();
        if ($round) {
            $round->update(['voting_started_at' => now()]);
        }

        // Show all hints from all cycles during voting
        $allHintsByCycle = $round ? $this->formatHintsByCycle($round->fresh()) : [];

        broadcast(new GameEvent($room->id, 'voting_started', [
            'room' => $this->formatRoom($room->fresh()),
            'round' => $round ? $this->formatRound($round) : null,
            'hints' => $round ? $this->formatHints($round->hints) : [],
            'previous_hints_by_cycle' => $allHintsByCycle,
            'hint_cycle' => $round ? ($round->hint_cycle ?? 1) : 1,
        ]));

        return ['status' => 'voting'];
    }

    /**
     * Submit a vote for a player in a round. Check if all votes are submitted.
     *
     * @return array Data for broadcasting to frontend.
     */
    public function submitVote(int $roundId, int $voterId, int $targetId): array
    {
        $round = Round::findOrFail($roundId);
        $room = $round->room;

        // Spectators cannot vote
        $voter = Player::find($voterId);
        if ($voter && $voter->is_spectator) {
            throw new \Exception(__('errors.not_playing'));
        }

        // Check if vote already submitted
        $existing = Vote::where('round_id', $roundId)
            ->where('voter_id', $voterId)
            ->first();

        if ($existing) {
            throw new \Exception(__('errors.vote_submitted'));
        }

        Vote::create([
            'round_id' => $roundId,
            'voter_id' => $voterId,
            'target_id' => $targetId,
        ]);

        $room->touchActivity();
        $activePlayerCount = $room->players()->where('is_spectator', false)->count();
        $allVotesSubmitted = $round->votes()->count() >= $activePlayerCount;

        $voter = Player::find($voterId);

        if ($allVotesSubmitted) {
            $results = $this->resolveVotes($room->id);

            broadcast(new GameEvent($room->id, 'vote_submitted', [
                'room' => $this->formatRoom($room->fresh()),
                'voter_id' => $voterId,
                'nickname' => $voter->nickname,
                'votes_count' => $round->votes()->count(),
                'total_players' => $room->players()->count(),
            ]));

            return $results;
        }

        broadcast(new GameEvent($room->id, 'vote_submitted', [
            'room' => $this->formatRoom($room->fresh()),
            'voter_id' => $voterId,
            'nickname' => $voter->nickname,
            'votes_count' => $round->votes()->count(),
            'total_players' => $room->players()->count(),
        ]));

        return [
            'all_votes_submitted' => $allVotesSubmitted,
            'votes_count' => $round->votes()->count(),
        ];
    }

    /**
     * Force-resolve votes when the voting timer expires.
     * Resolves with whatever votes are currently in (non-voters abstain).
     */
    public function timeoutVotes(int $roomId): array
    {
        $room = Room::findOrFail($roomId);

        if ($room->status !== 'voting') {
            return ['status' => 'not_voting'];
        }

        $currentRound = $room->rounds()
            ->where('round_number', $room->current_round)
            ->first();

        if (! $currentRound) {
            return ['status' => 'no_round'];
        }

        // Check that voting_started_at exists and 30s have passed
        if ($currentRound->voting_started_at && $currentRound->voting_started_at->diffInSeconds(now()) < 30) {
            return ['status' => 'timer_not_expired'];
        }

        return $this->resolveVotes($roomId);
    }

    /**
     * Resolve votes for the current round, determine the winner, and handle game progression.
     *
     * @return array Data for broadcasting to frontend.
     */
    public function resolveVotes(int $roomId): array
    {
        return DB::transaction(function () use ($roomId) {
            $room = Room::with('players')->findOrFail($roomId);
            $currentRound = $room->rounds()
                ->where('round_number', $room->current_round)
                ->firstOrFail();

            $votes = $currentRound->votes()->with('target')->get();

            // Tally votes: count votes for each target
            $tally = [];
            foreach ($votes as $vote) {
                $targetId = $vote->target_id;
                $tally[$targetId] = ($tally[$targetId] ?? 0) + 1;
            }

            // Find the max vote count
            $maxVotes = max($tally);

            // Find all players with the max vote count (for tie handling)
            $topTargets = array_keys(array_filter($tally, fn ($count) => $count === $maxVotes));

            $imposter = $room->players()->where('is_imposter', true)->first();

            // Determine if the imposter was caught
            $imposterCaught = in_array($imposter->id, $topTargets) && count($topTargets) === 1;

            // Determine if it's a tie involving the imposter
            $isTie = count($topTargets) > 1;

            // Calculate scores
            $roundResults = [
                'round_number' => $currentRound->round_number,
                'real_word' => $currentRound->real_word,
                'imposter_hint' => $currentRound->imposter_hint,
                'imposter' => $this->formatPlayer($imposter),
                'vote_tally' => [],
                'imposter_caught' => $imposterCaught,
                'is_tie' => $isTie,
                'winner' => null,
            ];

            // Format vote tally
            foreach ($tally as $targetId => $count) {
                $target = Player::find($targetId);
                $roundResults['vote_tally'][] = [
                    'player' => $this->formatPlayer($target),
                    'votes' => $count,
                ];
            }

            // Award points
            if ($imposterCaught) {
                // Crew wins: imposter was correctly identified
                foreach ($room->players as $player) {
                    if ($player->id === $imposter->id) {
                        continue;
                    }
                    // Voters who voted for imposter get bonus points
                    $votedForImposter = $votes->contains(fn ($v) => $v->voter_id === $player->id && $v->target_id === $imposter->id);
                    $points = $votedForImposter ? 2 : 1;
                    $player->increment('score', $points);
                }
                $roundResults['winner'] = 'crew';
            } elseif ($isTie) {
                // Tie: no one gets bonus, imposter survives
                $imposter->increment('score', 1);
                $roundResults['winner'] = 'tie';
            } else {
                // Imposter wins: was not caught (majority voted for someone else)
                $imposter->increment('score', 3);
                $roundResults['winner'] = 'imposter';
            }

            // Save results on the round itself
            $currentRound->update([
                'winner' => $roundResults['winner'],
                'imposter_caught' => $imposterCaught,
                'vote_tally' => $roundResults['vote_tally'],
            ]);

            // Award credit rewards when game ends
            $isGameOver = $currentRound->round_number >= $room->rounds_per_game;

            if ($isGameOver) {
                $room->update(['status' => 'finished']);

                $players = $room->players()->orderByDesc('score')->get();

                // Credit rewards for registered users
                $winner = $roundResults['winner'];
                foreach ($room->players as $player) {
                    if (! $player->user_id) {
                        continue;
                    }
                    try {
                        $user = $player->user;
                        $this->creditService->rewardGameEvent($user, 'game_played');

                        if ($player->is_imposter && $winner === 'imposter') {
                            $this->creditService->rewardGameEvent($user, 'win_as_imposter');
                        } elseif (! $player->is_imposter && $winner === 'crew') {
                            $this->creditService->rewardGameEvent($user, 'win_as_crew');
                        }

                        // Correct vote: crew member who voted for the imposter (only when imposter was caught)
                        if ($imposterCaught && ! $player->is_imposter) {
                            $votedCorrectly = $votes->contains(
                                fn ($v) => $v->voter_id === $player->id && $v->target_id === $imposter->id
                            );
                            if ($votedCorrectly) {
                                $this->creditService->rewardGameEvent($user, 'correct_vote');
                            }
                        }
                    } catch (\Throwable $e) {
                        Log::warning('Credit reward failed', [
                            'player_id' => $player->id,
                            'user_id' => $player->user_id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                // Track game stats and history
                $this->trackGameStats($room, $roundResults['winner']);
                broadcast(new GameEvent($room->id, 'game_over', [
                    'room' => $this->formatRoom($room->fresh()),
                    'round_results' => $roundResults,
                    'final_scores' => $this->formatPlayers($players),
                    'is_game_over' => true,
                ]));

                return [
                    'round_results' => $roundResults,
                    'is_game_over' => true,
                    'final_scores' => $this->formatPlayers($players),
                ];
            }

            // Non-final round: pause at round_result so players see the results
            $room->update(['status' => 'round_result']);

            broadcast(new GameEvent($room->id, 'round_result', [
                'room' => $this->formatRoom($room->fresh()),
                'round_results' => $roundResults,
                'is_game_over' => false,
            ]));

            return [
                'round_results' => $roundResults,
                'is_game_over' => false,
            ];
        });
    }

    /**
     * Advance from round_result to the next round.
     * Called when the creator clicks "Next Round" on the result screen.
     */
    public function advanceToNextRound(int $roomId, int $creatorId): array
    {
        return DB::transaction(function () use ($roomId, $creatorId) {
            $room = Room::with('players')->findOrFail($roomId);

            if ($room->creator_id !== $creatorId) {
                throw new \Exception(__('errors.creator_only'));
            }

            if ($room->status !== 'round_result') {
                throw new \Exception(__('errors.not_round_result'));
            }

            $previousRound = $room->rounds()
                ->where('round_number', $room->current_round)
                ->first();

            $nextRoundNumber = $previousRound->round_number + 1;

            // Convert spectators to regular players for the next round
            $room->players()->where('is_spectator', true)->update([
                'is_spectator' => false,
                'is_ready' => true,
            ]);

            // Reset imposter status
            $room->players()->update(['is_imposter' => false]);

            // Pull the next word/hint from the pre-generated pool
            $pool = $room->word_pool ?? [];
            if (! empty($pool)) {
                $generated = array_shift($pool);
            } else {
                $usedWords = $room->rounds()->pluck('real_word')->toArray();
                $generated = $this->aiWordService->generateWord($usedWords, $room->language, $room->category, $room->difficulty ?? 'medium');
            }

            // Assign new imposter randomly
            $newImposter = $room->fresh()->players->random();
            $newImposter->update(['is_imposter' => true]);

            $hintOrder = $room->fresh()->players->shuffle()->pluck('id')->toArray();

            $nextRound = Round::create([
                'room_id' => $room->id,
                'round_number' => $nextRoundNumber,
                'real_word' => $generated['word'],
                'imposter_hint' => $generated['hint'],
                'imposter_id' => $newImposter->id,
                'hint_order' => $hintOrder,
                'hint_cycle' => 1,
                'turn_started_at' => now(),
            ]);

            $room->update([
                'current_round' => $nextRoundNumber,
                'status' => 'playing',
                'word_pool' => array_values($pool),
            ]);

            broadcast(new GameEvent($room->id, 'next_round', [
                'room' => $this->formatRoom($room->fresh()),
                'round' => $this->formatRound($nextRound),
            ]));

            return [
                'room' => $this->formatRoom($room->fresh()),
                'round' => $this->formatRound($nextRound),
            ];
        });
    }

    /**
     * Rematch: reset the room back to waiting state so the same group can play again.
     * Only the creator can trigger this, and the room must be in 'finished' status.
     * The room code stays the same — all players return to the lobby.
     */
    public function rematch(int $roomId, int $creatorId): array
    {
        return DB::transaction(function () use ($roomId, $creatorId) {
            $room = Room::with('players')->findOrFail($roomId);

            if ($room->creator_id !== $creatorId) {
                throw new \Exception(__('errors.creator_only'));
            }

            if ($room->status !== 'finished') {
                throw new \Exception(__('errors.game_not_finished'));
            }

            // Delete all rounds (hints and votes cascade-delete via FK)
            $room->rounds()->delete();

            // Reset room state
            $room->update([
                'status' => 'waiting',
                'current_round' => 0,
                'word_pool' => null,
                'phase_votes' => null,
            ]);

            // Reset all players (including converting any spectators)
            $room->players()->update([
                'is_ready' => false,
                'is_imposter' => false,
                'is_spectator' => false,
                'score' => 0,
            ]);

            $room->touchActivity();

            $room = $room->fresh();

            // Broadcast rematch event so all players redirect to the lobby
            broadcast(new GameEvent($room->id, 'rematch', [
                'room' => $this->formatRoom($room),
            ]));

            // If public room, re-add to public rooms list
            if ($room->type === 'public') {
                broadcast(new RoomListEvent('created', $this->formatRoom($room)));
            }

            return [
                'room' => $this->formatRoom($room),
            ];
        });
    }

    /**
     * Get all public rooms with 'waiting' status.
     *
     * @return Collection
     */
    public function getPublicRooms(): \Illuminate\Support\Collection
    {
        $this->cleanAllStale();

        return Room::where('status', 'waiting')
            ->where('type', 'public')
            ->withCount('players')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($room) => $this->formatRoom($room));
    }

    /**
     * Get the full game state for a specific player in a room.
     * Spectators see the real word, all hints, votes, and imposter identity.
     *
     * @return array The game state tailored for the requesting player.
     */
    public function getGameState(int $roomId, int $playerId): array
    {
        $room = Room::with(['players', 'rounds'])->findOrFail($roomId);
        $player = Player::findOrFail($playerId);

        if ($player->room_id !== $roomId) {
            throw new \Exception(__('errors.player_not_in_room'));
        }

        // Touch this player's heartbeat before purging — they just loaded the page
        $player->update(['last_heartbeat_at' => now()]);
        $player->refresh();

        $this->purgeStalePlayers($room);
        $room = $room->fresh();

        // Re-verify player still exists after purge (edge case: deleted by someone else)
        $player = Player::find($playerId);
        if (! $player || $player->room_id !== $roomId) {
            throw new \Exception(__('errors.player_not_in_room'));
        }

        $isSpectator = $player->is_spectator;

        $state = [
            'room' => $this->formatRoom($room),
            'players' => $this->formatPlayers($room->players),
            'player' => $this->formatPlayer($player),
            'current_round' => null,
            'hints' => [],
            'previous_hints_by_cycle' => [],
            'hint_cycle' => 1,
            'votes' => [],
            'word' => null,
            'hint_for_imposter' => null,
            'phase_votes' => $room->phase_votes,
            'turn_started_at' => null,
            'voting_started_at' => null,
        ];

        if (in_array($room->status, ['playing', 'voting']) && $room->current_round > 0) {
            $currentRound = $room->rounds()
                ->where('round_number', $room->current_round)
                ->first();

            if ($currentRound) {
                $state['current_round'] = $this->formatRound($currentRound);
                $state['turn_started_at'] = $currentRound->turn_started_at?->toISOString();
                $state['voting_started_at'] = $currentRound->voting_started_at?->toISOString();

                // Spectators see the real word AND the imposter hint
                if ($isSpectator) {
                    $state['word'] = $currentRound->real_word;
                    $state['hint_for_imposter'] = $currentRound->imposter_hint;
                    $state['spectator_imposter'] = $this->formatPlayer(
                        $room->players()->where('is_imposter', true)->first()
                            ?? Player::find($currentRound->imposter_id)
                    );
                } elseif ($player->is_imposter) {
                    $state['hint_for_imposter'] = $currentRound->imposter_hint;
                    $state['word'] = null;
                } else {
                    $state['word'] = $currentRound->real_word;
                    $state['hint_for_imposter'] = null;
                }

                $currentCycle = $currentRound->hint_cycle ?? 1;
                $state['hint_cycle'] = $currentCycle;

                // Use active (non-spectator) player count for hints/votes completion
                // Get hints from the current cycle only for the main hints array
                $currentCycleHints = $currentRound->hints()->where('hint_cycle', $currentCycle)->get();
                $activePlayerCount = $room->players()->where('is_spectator', false)->count();
                $allHintsSubmitted = $currentCycleHints->count() >= $activePlayerCount;

                // Show current-cycle hints in the hint_order sequence
                $hintOrder = $currentRound->hint_order ?? [];
                $submittedHints = $currentCycleHints->keyBy('player_id');
                $orderedHints = [];
                foreach ($hintOrder as $pid) {
                    if (isset($submittedHints[$pid])) {
                        $hint = $submittedHints[$pid];
                        $orderedHints[] = [
                            'id' => $hint->id,
                            'player_id' => $hint->player_id,
                            'player_nickname' => $hint->player?->nickname,
                            'content' => $hint->content,
                        ];
                    }
                }
                $state['hints'] = $orderedHints;

                // Build previous hints grouped by cycle (for display in the UI)
                $state['previous_hints_by_cycle'] = $this->formatHintsByCycle($currentRound);

                // Add hint order and current turn info
                $state['hint_order'] = $hintOrder;
                $submittedCount = $currentCycleHints->count();
                $state['current_turn_player_id'] = $allHintsSubmitted
                    ? null
                    : ($hintOrder[$submittedCount] ?? null);
                $state['hints_complete'] = $allHintsSubmitted;

                // Get votes — spectators see all votes regardless of completion
                $votes = $currentRound->votes;
                $allVotesSubmitted = $votes->count() >= $activePlayerCount;

                if ($allVotesSubmitted || $isSpectator) {
                    $state['votes'] = $votes->map(fn ($vote) => [
                        'voter_id' => $vote->voter_id,
                        'target_id' => $vote->target_id,
                    ])->toArray();
                } else {
                    // Only reveal who voted, not who they voted for
                    $state['votes'] = $votes->map(fn ($vote) => [
                        'voter_id' => $vote->voter_id,
                        'submitted' => true,
                    ])->toArray();
                }
            }
        }

        // Handle finished state - provide winner and imposter data
        if ($room->status === 'finished') {
            $lastRound = $room->rounds()->orderByDesc('round_number')->first();
            $imposter = $room->players()->where('is_imposter', true)->first();

            // Fallback: if the imposter was deleted (fled), reconstruct from round data
            if (! $imposter && $lastRound) {
                $imposterPlayer = Player::find($lastRound->imposter_id);
                $imposter = $imposterPlayer;
            }

            $allHints = $lastRound ? $lastRound->hints : collect();
            $allVotes = $lastRound ? $lastRound->votes : collect();

            $state['word'] = $lastRound?->real_word;
            $state['imposter_hint'] = $lastRound?->imposter_hint;
            $state['imposter'] = $imposter ? $this->formatPlayer($imposter) : null;
            $state['hints'] = $allHints ? $this->formatHints($allHints) : [];
            $state['previous_hints_by_cycle'] = $lastRound ? $this->formatHintsByCycle($lastRound) : [];
            $state['hint_cycle'] = $lastRound ? ($lastRound->hint_cycle ?? 1) : 1;
            $state['votes'] = $allVotes->map(fn ($vote) => [
                'voter_id' => $vote->voter_id,
                'target_id' => $vote->target_id,
            ])->toArray();

            // Use the round's stored winner if available (set during imposter_fled or normal resolve)
            if ($lastRound && $lastRound->winner) {
                $state['winner'] = $lastRound->winner;
                $state['imposter_caught'] = $lastRound->imposter_caught;
                $state['vote_tally'] = $lastRound->vote_tally;
            } elseif ($allVotes->isNotEmpty()) {
                $tally = $allVotes->groupBy('target_id')->map->count();
                $maxVotes = $tally->max();
                $topVoted = $tally->filter(fn ($count) => $count === $maxVotes);
                $imposterCaught = $topVoted->has($imposter?->id) && $topVoted->count() === 1;
                $state['winner'] = $imposterCaught ? 'crew' : 'imposter';
                $state['imposter_caught'] = $imposterCaught;

                // Build vote_tally from raw votes if not stored on the round
                $voteTally = [];
                foreach ($tally as $targetId => $count) {
                    $target = Player::find($targetId);
                    $voteTally[] = [
                        'player' => $this->formatPlayer($target),
                        'votes' => $count,
                    ];
                }
                $state['vote_tally'] = $voteTally;
            }

            $state['is_game_over'] = true;
        }
        if ($room->status === 'round_result') {
            $lastRound = $room->rounds()->where('round_number', $room->current_round)->first();
            if ($lastRound) {
                $imposterPlayer = Player::find($lastRound->imposter_id);
                $state['word'] = $lastRound->real_word;
                $state['imposter_hint'] = $lastRound->imposter_hint;
                $state['imposter'] = $imposterPlayer ? $this->formatPlayer($imposterPlayer) : null;
                $state['winner'] = $lastRound->winner;
                $state['imposter_caught'] = $lastRound->imposter_caught;
                $state['vote_tally'] = $lastRound->vote_tally;
                $state['hints'] = $this->formatHints($lastRound->hints);
                $state['previous_hints_by_cycle'] = $this->formatHintsByCycle($lastRound);
                $state['hint_cycle'] = $lastRound->hint_cycle ?? 1;
                $state['current_round'] = $this->formatRound($lastRound);
                $state['is_game_over'] = false;

                // Include raw votes for "who voted for whom" display
                $roundVotes = $lastRound->votes;
                $state['votes'] = $roundVotes->map(fn ($vote) => [
                    'voter_id' => $vote->voter_id,
                    'target_id' => $vote->target_id,
                ])->toArray();
            }
        }

        // Include recent chat messages (last 50) for this room
        $chatMessages = ChatMessage::where('room_id', $roomId)
            ->with('player')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->reverse()
            ->values()
            ->map(fn ($msg) => [
                'id' => $msg->id,
                'player' => [
                    'id' => $msg->player->id,
                    'nickname' => $msg->player->nickname,
                    'avatar' => $msg->player->avatar,
                ],
                'message' => $msg->message,
                'created_at' => $msg->created_at->toISOString(),
            ])
            ->toArray();

        $state['chat_messages'] = $chatMessages;

        return $state;
    }

    /**
     * Reconnect a returning player whose session has a player_id/room_id but
     * the player record was deleted (e.g. stale cleanup while they were away).
     * If during an active round: reconnect as spectator, play next round.
     * If between rounds (round_result): reconnect as normal player.
     *
     * @return array The reconnected player and updated game state.
     *
     * @throws \Exception
     */
    public function reconnectPlayer(int $roomId, string $nickname, ?array $avatar = null, ?int $userId = null): array
    {
        return DB::transaction(function () use ($roomId, $nickname, $avatar, $userId) {
            $room = Room::findOrFail($roomId);

            $isDuringActiveRound = in_array($room->status, ['playing', 'voting']);
            $isBetweenRounds = $room->status === 'round_result';

            $player = Player::create([
                'nickname' => $nickname,
                'room_id' => $room->id,
                'user_id' => $userId,
                'is_ready' => ! $isDuringActiveRound,
                'is_imposter' => false,
                'is_spectator' => $isDuringActiveRound,
                'score' => 0,
                'avatar' => $avatar,
            ]);

            $room->touchActivity();

            broadcast(new GameEvent($room->id, $isDuringActiveRound ? 'spectator_joined' : 'player_joined', [
                'room' => $this->formatRoom($room->fresh()),
                'player' => $this->formatPlayer($player),
            ]));

            return [
                'room' => $this->formatRoom($room->fresh()),
                'player' => $this->formatPlayer($player),
            ];
        });
    }

    /**
     * Format a room for API response.
     */
    private function formatRoom(Room $room): array
    {
        return [
            'id' => $room->id,
            'code' => $room->code,
            'type' => $room->type,
            'status' => $room->status,
            'max_players' => $room->max_players,
            'rounds_per_game' => $room->rounds_per_game,
            'language' => $room->language,
            'category' => $room->category,
            'difficulty' => $room->difficulty,
            'current_round' => $room->current_round,
            'creator_id' => $room->creator_id,
            'players_count' => $room->players()->count(),
            'phase_votes' => $room->phase_votes,
            'created_at' => $room->created_at?->toISOString(),
        ];
    }

    /**
     * Format a player for API response.
     */
    private function formatPlayer(Player $player): array
    {
        return [
            'id' => $player->id,
            'nickname' => $player->nickname,
            'room_id' => $player->room_id,
            'is_ready' => $player->is_ready,
            'is_imposter' => $player->is_imposter,
            'is_spectator' => $player->is_spectator,
            'score' => $player->score,
            'avatar' => $player->avatar,
        ];
    }

    /**
     * Format a collection of players for API response.
     */
    private function formatPlayers($players): array
    {
        return $players->map(fn ($player) => $this->formatPlayer($player))->toArray();
    }

    /**
     * Format a round for API response.
     */
    private function formatRound(Round $round): array
    {
        return [
            'id' => $round->id,
            'room_id' => $round->room_id,
            'round_number' => $round->round_number,
            'hint_order' => $round->hint_order,
            'hint_cycle' => $round->hint_cycle ?? 1,
            'turn_started_at' => $round->turn_started_at?->toISOString(),
            'voting_started_at' => $round->voting_started_at?->toISOString(),
            'created_at' => $round->created_at?->toISOString(),
        ];
    }

    /**
     * Format hints for API response.
     */
    private function formatHints($hints): array
    {
        return $hints->map(fn ($hint) => $this->formatSingleHint($hint))->toArray();
    }

    /**
     * Format a single hint for API response.
     */
    private function formatSingleHint(Hint $hint): array
    {
        return [
            'id' => $hint->id,
            'player_id' => $hint->player_id,
            'player_nickname' => $hint->player?->nickname,
            'hint_cycle' => $hint->hint_cycle ?? 1,
            'content' => $hint->content,
        ];
    }

    /**
     * Format hints for a specific cycle, ordered by hint_order.
     */
    private function formatHintsForCycle(Round $round, int $cycle): array
    {
        $cycleHints = $round->hints()->where('hint_cycle', $cycle)->get();
        $hintOrder = $round->hint_order ?? [];
        $submittedHints = $cycleHints->keyBy('player_id');
        $orderedHints = [];

        foreach ($hintOrder as $pid) {
            if (isset($submittedHints[$pid])) {
                $orderedHints[] = $this->formatSingleHint($submittedHints[$pid]);
            }
        }

        return $orderedHints;
    }

    /**
     * Format all hints for a round grouped by cycle number.
     * Returns an associative array: [ cycle_number => [hint, hint, ...], ... ]
     */
    private function formatHintsByCycle(Round $round): array
    {
        $allHints = $round->hints()->orderBy('hint_cycle')->orderBy('id')->get();
        $grouped = [];

        foreach ($allHints as $hint) {
            $cycle = $hint->hint_cycle ?? 1;
            if (! isset($grouped[$cycle])) {
                $grouped[$cycle] = [];
            }
            $grouped[$cycle][] = $this->formatSingleHint($hint);
        }

        return $grouped;
    }

    /**
     * Track game statistics and history for all players when a game ends.
     * Upserts GameStat (aggregate) and creates GameHistory records (per-game).
     */
    private function trackGameStats(Room $room, string $winner): void
    {
        try {
            $players = $room->players()->where('is_spectator', false)->get();
            $lastRound = $room->rounds()->orderByDesc('round_number')->first();
            $word = $lastRound?->real_word;
            $roundsPlayed = $room->current_round;

            foreach ($players as $player) {
                $isImposter = $player->is_imposter;
                $won = false;
                if ($isImposter && $winner === 'imposter') {
                    $won = true;
                } elseif (! $isImposter && $winner === 'crew') {
                    $won = true;
                }

                // Upsert aggregate stats (GameStat)
                if ($player->user_id) {
                    $stat = GameStat::firstOrNew(['user_id' => $player->user_id]);
                    $stat->nickname = $player->nickname;
                } else {
                    $stat = GameStat::firstOrNew(['nickname' => $player->nickname]);
                }
                $stat->games_played = ($stat->games_played ?? 0) + 1;
                if ($won && ! $isImposter) {
                    $stat->wins_as_crew = ($stat->wins_as_crew ?? 0) + 1;
                } elseif ($won && $isImposter) {
                    $stat->wins_as_imposter = ($stat->wins_as_imposter ?? 0) + 1;
                }
                $stat->save();

                // Create per-game history record (GameHistory)
                GameHistory::create([
                    'room_code' => $room->code,
                    'player_nickname' => $player->nickname,
                    'user_id' => $player->user_id,
                    'role' => $isImposter ? 'imposter' : 'crew',
                    'word' => $word,
                    'won' => $won,
                    'score' => $player->score ?? 0,
                    'rounds_played' => $roundsPlayed,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to track game stats', [
                'room_id' => $room->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
