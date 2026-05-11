<?php

namespace App\Services;

use App\Events\GameEvent;
use App\Models\Hint;
use App\Models\Player;
use App\Models\Room;
use App\Models\Round;
use App\Models\Vote;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GameService
{
    public function __construct(
        private AiWordService $aiWordService,
    ) {}

    /**
     * Create a new game room and add the creator as the first player.
     *
     * @return array Data for broadcasting to frontend.
     */
    public function createRoom(string $nickname, string $type, int $maxPlayers, int $roundsPerGame): array
    {
        return DB::transaction(function () use ($nickname, $type, $maxPlayers, $roundsPerGame) {
            $room = Room::create([
                'code' => Room::generateCode(),
                'type' => $type,
                'status' => 'waiting',
                'max_players' => $maxPlayers,
                'rounds_per_game' => $roundsPerGame,
                'current_round' => 0,
            ]);

            $player = Player::create([
                'nickname' => $nickname,
                'room_id' => $room->id,
                'is_ready' => false,
                'is_imposter' => false,
                'score' => 0,
            ]);

            $room->update(['creator_id' => $player->id]);

            broadcast(new GameEvent($room->id, 'room_created', [
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
     * Join an existing room by code.
     *
     * @return array Data for broadcasting to frontend.
     *
     * @throws \Exception
     */
    public function joinRoom(string $code, string $nickname): array
    {
        $room = Room::where('code', strtoupper($code))->first();

        if (! $room) {
            throw new \Exception('Room not found.');
        }

        if ($room->status !== 'waiting') {
            throw new \Exception('Game already in progress.');
        }

        if ($room->players()->count() >= $room->max_players) {
            throw new \Exception('Room is full.');
        }

        if ($room->players()->where('nickname', $nickname)->exists()) {
            throw new \Exception('Nickname already taken in this room.');
        }

        $player = Player::create([
            'nickname' => $nickname,
            'room_id' => $room->id,
            'is_ready' => false,
            'is_imposter' => false,
            'score' => 0,
        ]);

        broadcast(new GameEvent($room->id, 'player_joined', [
            'room' => $this->formatRoom($room->fresh()),
            'player' => $this->formatPlayer($player),
        ]));

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
                throw new \Exception('Game already in progress.');
            }

            $players = $room->players;
            if ($players->count() < 3) {
                throw new \Exception('Need at least 3 players to start.');
            }

            // Reset all players' imposter status from any prior rounds
            $room->players()->update(['is_imposter' => false]);

            // Gather used words from previous rounds in this room
            $usedWords = $room->rounds()->pluck('real_word')->toArray();

            // Generate word via AI
            $generated = $this->aiWordService->generateWord($usedWords);

            // Assign imposter randomly
            $imposter = $players->random();
            $imposter->update(['is_imposter' => true]);

            // Create the first round
            $roundNumber = 1;
            $round = Round::create([
                'room_id' => $room->id,
                'round_number' => $roundNumber,
                'real_word' => $generated['word'],
                'imposter_hint' => $generated['hint'],
            ]);

            $room->update([
                'status' => 'playing',
                'current_round' => $roundNumber,
            ]);

            broadcast(new GameEvent($room->id, 'game_started', [
                'room' => $this->formatRoom($room->fresh()),
                'round' => $this->formatRound($round),
            ]));

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

        // Check if hint already submitted
        $existing = Hint::where('round_id', $roundId)
            ->where('player_id', $playerId)
            ->first();

        if ($existing) {
            throw new \Exception('Hint already submitted for this round.');
        }

        Hint::create([
            'round_id' => $roundId,
            'player_id' => $playerId,
            'content' => trim($content),
        ]);

        $player = Player::find($playerId);
        $allHintsSubmitted = $round->hints()->count() >= $room->players()->count();

        if ($allHintsSubmitted) {
            // Check if this was the last round
            if ($round->round_number >= $room->rounds_per_game) {
                // Transition to voting phase
                $room->update(['status' => 'voting']);

                broadcast(new GameEvent($room->id, 'voting_started', [
                    'room' => $this->formatRoom($room->fresh()),
                    'round' => $this->formatRound($round->fresh()),
                    'hints' => $this->formatHints($round->fresh()->hints),
                ]));
            } else {
                // Advance to next hint round
                $nextRoundNumber = $round->round_number + 1;
                $usedWords = $room->rounds()->pluck('real_word')->toArray();
                $generated = $this->aiWordService->generateWord($usedWords);

                $nextRound = Round::create([
                    'room_id' => $room->id,
                    'round_number' => $nextRoundNumber,
                    'real_word' => $generated['word'],
                    'imposter_hint' => $generated['hint'],
                ]);

                $room->update(['current_round' => $nextRoundNumber]);

                broadcast(new GameEvent($room->id, 'round_complete', [
                    'room' => $this->formatRoom($room->fresh()),
                    'round' => $this->formatRound($round->fresh()),
                    'next_round' => $this->formatRound($nextRound),
                    'hints' => $this->formatHints($round->fresh()->hints),
                    'all_hints_submitted' => true,
                ]));
            }
        } else {
            broadcast(new GameEvent($room->id, 'hint_submitted', [
                'room' => $this->formatRoom($room->fresh()),
                'player_id' => $playerId,
                'nickname' => $player->nickname,
                'hints_count' => $round->hints()->count(),
                'total_players' => $room->players()->count(),
            ]));
        }

        return [
            'all_hints_submitted' => $allHintsSubmitted,
            'hints_count' => $round->hints()->count(),
        ];
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

        // Check if vote already submitted
        $existing = Vote::where('round_id', $roundId)
            ->where('voter_id', $voterId)
            ->first();

        if ($existing) {
            throw new \Exception('Vote already submitted for this round.');
        }

        Vote::create([
            'round_id' => $roundId,
            'voter_id' => $voterId,
            'target_id' => $targetId,
        ]);

        $allVotesSubmitted = $round->votes()->count() >= $room->players()->count();

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

            // Check if game is over or advance to next round
            $isGameOver = $currentRound->round_number >= $room->rounds_per_game;

            if ($isGameOver) {
                $room->update(['status' => 'finished']);

                $players = $room->players()->orderByDesc('score')->get();

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

            // Advance to next round
            $nextRoundNumber = $currentRound->round_number + 1;

            // Reset imposter status
            $room->players()->update(['is_imposter' => false]);

            // Generate new word for next round
            $usedWords = $room->rounds()->pluck('real_word')->toArray();
            $generated = $this->aiWordService->generateWord($usedWords);

            // Assign new imposter
            $newImposter = $room->fresh()->players->random();
            $newImposter->update(['is_imposter' => true]);

            $nextRound = Round::create([
                'room_id' => $room->id,
                'round_number' => $nextRoundNumber,
                'real_word' => $generated['word'],
                'imposter_hint' => $generated['hint'],
            ]);

            $room->update(['current_round' => $nextRoundNumber]);

            broadcast(new GameEvent($room->id, 'voting_complete', [
                'room' => $this->formatRoom($room->fresh()),
                'round_results' => $roundResults,
                'next_round' => $this->formatRound($nextRound),
                'is_game_over' => false,
            ]));

            return [
                'round_results' => $roundResults,
                'is_game_over' => false,
                'next_round' => $this->formatRound($nextRound),
                'new_imposter_id' => $newImposter->id,
                'new_real_word' => $generated['word'],
                'new_imposter_hint' => $generated['hint'],
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
        return Room::where('status', 'waiting')
            ->where('type', 'public')
            ->withCount('players')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($room) => $this->formatRoom($room));
    }

    /**
     * Get the full game state for a specific player in a room.
     *
     * @return array The game state tailored for the requesting player.
     */
    public function getGameState(int $roomId, int $playerId): array
    {
        $room = Room::with(['players', 'rounds'])->findOrFail($roomId);
        $player = Player::findOrFail($playerId);

        // Ensure player belongs to this room
        if ($player->room_id !== $roomId) {
            throw new \Exception('Player does not belong to this room.');
        }

        $state = [
            'room' => $this->formatRoom($room),
            'players' => $this->formatPlayers($room->players),
            'player' => $this->formatPlayer($player),
            'current_round' => null,
            'hints' => [],
            'votes' => [],
            'word' => null,
            'hint_for_imposter' => null,
        ];

        if (in_array($room->status, ['playing', 'voting']) && $room->current_round > 0) {
            $currentRound = $room->rounds()
                ->where('round_number', $room->current_round)
                ->first();

            if ($currentRound) {
                $state['current_round'] = $this->formatRound($currentRound);

                // If the player is the imposter, they get the hint instead of the real word
                if ($player->is_imposter) {
                    $state['hint_for_imposter'] = $currentRound->imposter_hint;
                    $state['word'] = null;
                } else {
                    $state['word'] = $currentRound->real_word;
                    $state['hint_for_imposter'] = null;
                }

                // Get hints (only show content if all submitted, otherwise just show who submitted)
                $hints = $currentRound->hints;
                $allHintsSubmitted = $hints->count() >= $room->players()->count();

                if ($allHintsSubmitted) {
                    $state['hints'] = $this->formatHints($hints);
                } else {
                    // Only reveal who submitted, not the content
                    $state['hints'] = $hints->map(fn ($hint) => [
                        'player_id' => $hint->player_id,
                        'submitted' => true,
                    ])->toArray();
                }

                // Get votes (only reveal after all votes are in)
                $votes = $currentRound->votes;
                $allVotesSubmitted = $votes->count() >= $room->players()->count();

                if ($allVotesSubmitted) {
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
            $allHints = $lastRound ? $lastRound->hints : collect();
            $allVotes = $lastRound ? $lastRound->votes : collect();

            $state['word'] = $lastRound?->real_word;
            $state['imposter_hint'] = $lastRound?->imposter_hint;
            $state['imposter'] = $imposter ? $this->formatPlayer($imposter) : null;
            $state['hints'] = $allHints ? $this->formatHints($allHints) : [];
            $state['votes'] = $allVotes->map(fn ($vote) => [
                'voter_id' => $vote->voter_id,
                'target_id' => $vote->target_id,
            ])->toArray();

            // Determine winner from votes
            if ($allVotes->isNotEmpty()) {
                $tally = $allVotes->groupBy('target_id')->map->count();
                $maxVotes = $tally->max();
                $topVoted = $tally->filter(fn ($count) => $count === $maxVotes);
                $imposterCaught = $topVoted->has($imposter?->id) && $topVoted->count() === 1;
                $state['winner'] = $imposterCaught ? 'crew' : 'imposter';
            }
        }

        return $state;
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
            'current_round' => $room->current_round,
            'creator_id' => $room->creator_id,
            'players_count' => $room->players()->count(),
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
            'score' => $player->score,
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
            'created_at' => $round->created_at?->toISOString(),
        ];
    }

    /**
     * Format hints for API response.
     */
    private function formatHints($hints): array
    {
        return $hints->map(fn ($hint) => [
            'id' => $hint->id,
            'player_id' => $hint->player_id,
            'player_nickname' => $hint->player?->nickname,
            'content' => $hint->content,
        ])->toArray();
    }
}
