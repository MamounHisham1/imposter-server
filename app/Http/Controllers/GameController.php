<?php

namespace App\Http\Controllers;

use App\Events\GameEvent;
use App\Models\ChatMessage;
use App\Models\Player;
use App\Models\Room;
use App\Services\GameService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class GameController extends Controller
{
    public function __construct(
        private GameService $gameService
    ) {}

    public function show(string $code)
    {
        $roomId = session('room_id');
        $playerId = session('player_id');

        if (! $roomId || ! $playerId) {
            return redirect()->route('home');
        }

        try {
            $state = $this->gameService->getGameState($roomId, $playerId);
        } catch (\Exception $e) {
            // Player was purged mid-game - attempt graceful rejoin
            $reconnected = $this->attemptGracefulRejoin($roomId, $playerId);
            if ($reconnected) {
                return redirect()->route('game.show', $code);
            }

            session()->forget(['player_id', 'room_id']);

            return redirect()->route('home')->withErrors(['error' => $e->getMessage()]);
        }

        if ($state['room']['status'] === 'voting') {
            return redirect()->route('vote.show', $code);
        }

        if ($state['room']['status'] === 'finished') {
            return redirect()->route('result.show', $code);
        }

        if ($state['room']['status'] === 'round_result') {
            return redirect()->route('result.show', $code);
        }

        if ($state['room']['status'] !== 'playing') {
            return redirect()->route('room.show', $code);
        }

        return Inertia::render('Game', $state);
    }

    /**
     * Reconnection endpoint for players who lost connection or refreshed.
     * Validates session, checks if player still exists, and if purged during
     * an active game, re-creates the player with the same nickname and avatar.
     */
    public function reconnect(Request $request, string $code)
    {
        $roomId = session('room_id');
        $playerId = session('player_id');

        if (! $roomId || ! $playerId) {
            throw ValidationException::withMessages([
                'error' => 'No active session found. Please rejoin the room.',
            ]);
        }

        $room = Room::where('code', strtoupper($code))->first();

        if (! $room) {
            session()->forget(['player_id', 'room_id']);
            throw ValidationException::withMessages([
                'error' => __('errors.room_not_found'),
            ]);
        }

        if ($room->id !== $roomId) {
            session()->forget(['player_id', 'room_id']);
            throw ValidationException::withMessages([
                'error' => __('errors.player_not_in_room'),
            ]);
        }

        // Check if player still exists in the room
        $player = Player::where('id', $playerId)->where('room_id', $roomId)->first();

        if ($player) {
            // Player still exists - just refresh heartbeat and redirect to correct page
            $player->update(['last_heartbeat_at' => now()]);

            return $this->redirectByRoomStatus($room, $code);
        }

        // Player was purged - attempt to rejoin if game is still active
        $isActiveGame = in_array($room->status, ['playing', 'voting', 'round_result']);

        if (! $isActiveGame) {
            session()->forget(['player_id', 'room_id']);
            throw ValidationException::withMessages([
                'error' => 'Game is no longer in progress. Please join a new room.',
            ]);
        }

        // Check room capacity (spectators get a more generous cap)
        $spectatorCount = $room->players()->where('is_spectator', true)->count();
        if ($room->players()->count() >= $room->max_players && $spectatorCount >= max(5, $room->max_players)) {
            session()->forget(['player_id', 'room_id']);
            throw ValidationException::withMessages([
                'error' => __('errors.room_full'),
            ]);
        }

        // Determine if returning mid-round (spectator) or between rounds (player)
        $isDuringActiveRound = in_array($room->status, ['playing', 'voting']);

        // Re-create the player
        $originalNickname = session('player_nickname', 'Player');
        $originalAvatar = session('player_avatar');

        $newPlayer = Player::create([
            'nickname' => $originalNickname,
            'room_id' => $roomId,
            'is_ready' => ! $isDuringActiveRound,
            'is_imposter' => false,
            'is_spectator' => $isDuringActiveRound,
            'score' => 0,
            'avatar' => $originalAvatar,
            'session_id' => 'rejoin:'.$playerId,
        ]);

        // Update session with new player ID
        session([
            'player_id' => $newPlayer->id,
            'player_nickname' => $originalNickname,
        ]);

        $newPlayer->update(['last_heartbeat_at' => now()]);

        return $this->redirectByRoomStatus($room->fresh(), $code);
    }

    public function submitHint(Request $request, string $code)
    {
        $playerId = $request->input('player_id') ?? session('player_id');
        $roomId = $request->input('room_id') ?? session('room_id');

        $validated = $request->validate([
            'content' => 'required|string|max:100',
        ]);

        if (! $playerId || ! $roomId) {
            return redirect()->route('home');
        }

        try {
            $state = $this->gameService->getGameState($roomId, $playerId);
        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'error' => $e->getMessage(),
            ]);
        }

        $roundId = $state['current_round']['id'] ?? null;

        if (! $roundId) {
            throw ValidationException::withMessages([
                'error' => __('errors.no_active_round'),
            ]);
        }

        try {
            $this->gameService->submitHint(
                $roundId,
                $playerId,
                $validated['content']
            );
        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'error' => $e->getMessage(),
            ]);
        }

        return back();
    }

    public function skipHint(Request $request, string $code)
    {
        $playerId = $request->input('player_id') ?? session('player_id');
        $roomId = $request->input('room_id') ?? session('room_id');

        if (! $playerId || ! $roomId) {
            return redirect()->route('home');
        }

        try {
            $state = $this->gameService->getGameState($roomId, $playerId);
        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'error' => $e->getMessage(),
            ]);
        }

        $roundId = $state['current_round']['id'] ?? null;

        if (! $roundId) {
            throw ValidationException::withMessages([
                'error' => __('errors.no_active_round'),
            ]);
        }

        try {
            $this->gameService->skipHint($roundId, $playerId);
        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'error' => $e->getMessage(),
            ]);
        }

        return back();
    }

    public function nextRound(Request $request, string $code)
    {
        $playerId = $request->input('player_id') ?? session('player_id');
        $roomId = $request->input('room_id') ?? session('room_id');

        if (! $playerId || ! $roomId) {
            return redirect()->route('home');
        }

        try {
            $this->gameService->advanceRound($roomId, $playerId);
        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'error' => $e->getMessage(),
            ]);
        }

        return back();
    }

    public function startVoting(Request $request, string $code)
    {
        $playerId = $request->input('player_id') ?? session('player_id');
        $roomId = $request->input('room_id') ?? session('room_id');

        if (! $playerId || ! $roomId) {
            return redirect()->route('home');
        }

        try {
            $this->gameService->startVoting($roomId, $playerId);
        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'error' => $e->getMessage(),
            ]);
        }

        return back();
    }

    public function phaseVote(Request $request, string $code)
    {
        $playerId = $request->input('player_id') ?? session('player_id');
        $roomId = $request->input('room_id') ?? session('room_id');

        $validated = $request->validate([
            'choice' => 'required|in:vote,continue',
        ]);

        if (! $playerId || ! $roomId) {
            return redirect()->route('home');
        }

        try {
            $this->gameService->submitPhaseVote($roomId, $playerId, $validated['choice']);
        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'error' => $e->getMessage(),
            ]);
        }

        return back();
    }

    public function vote(string $code)
    {
        $roomId = session('room_id');
        $playerId = session('player_id');

        if (! $roomId || ! $playerId) {
            return redirect()->route('home');
        }

        try {
            $state = $this->gameService->getGameState($roomId, $playerId);
        } catch (\Exception $e) {
            // Attempt graceful rejoin for vote page too
            $reconnected = $this->attemptGracefulRejoin($roomId, $playerId);
            if ($reconnected) {
                return redirect()->route('vote.show', $code);
            }

            session()->forget(['player_id', 'room_id']);

            return redirect()->route('home')->withErrors(['error' => $e->getMessage()]);
        }

        if ($state['room']['status'] === 'finished') {
            return redirect()->route('result.show', $code);
        }

        if ($state['room']['status'] !== 'voting') {
            return redirect()->route('game.show', $code);
        }

        return Inertia::render('Vote', $state);
    }

    public function submitVote(Request $request, string $code)
    {
        $playerId = $request->input('player_id') ?? session('player_id');
        $roomId = $request->input('room_id') ?? session('room_id');

        $validated = $request->validate([
            'target_id' => 'required|integer|exists:players,id',
        ]);

        if (! $playerId || ! $roomId) {
            return redirect()->route('home');
        }

        try {
            $state = $this->gameService->getGameState($roomId, $playerId);
        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'error' => $e->getMessage(),
            ]);
        }

        $roundId = $state['current_round']['id'] ?? null;

        if (! $roundId) {
            throw ValidationException::withMessages([
                'error' => __('errors.no_active_round'),
            ]);
        }

        try {
            $this->gameService->submitVote($roundId, $playerId, $validated['target_id']);
        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'error' => $e->getMessage(),
            ]);
        }

        return back();
    }

    public function timeoutVote(Request $request, string $code)
    {
        $roomId = $request->input('room_id') ?? session('room_id');

        if (! $roomId) {
            return redirect()->route('home');
        }

        try {
            $this->gameService->timeoutVotes($roomId);
        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'error' => $e->getMessage(),
            ]);
        }

        return back();
    }

    public function result(string $code)
    {
        $roomId = session('room_id');
        $playerId = session('player_id');

        if (! $roomId || ! $playerId) {
            return redirect()->route('home');
        }

        try {
            $state = $this->gameService->getGameState($roomId, $playerId);
        } catch (\Exception $e) {
            // Attempt graceful rejoin for result page too
            $reconnected = $this->attemptGracefulRejoin($roomId, $playerId);
            if ($reconnected) {
                return redirect()->route('result.show', $code);
            }

            session()->forget(['player_id', 'room_id']);

            return redirect()->route('home')->withErrors(['error' => $e->getMessage()]);
        }

        return Inertia::render('Result', $state);
    }

    public function nextRoundFromResult(Request $request, string $code)
    {
        $playerId = $request->input('player_id') ?? session('player_id');
        $roomId = $request->input('room_id') ?? session('room_id');

        if (! $playerId || ! $roomId) {
            return redirect()->route('home');
        }

        try {
            $this->gameService->advanceToNextRound($roomId, $playerId);
        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'error' => $e->getMessage(),
            ]);
        }

        return redirect()->route('game.show', $code);
    }

    public function rematch(Request $request, string $code)
    {
        $playerId = $request->input('player_id') ?? session('player_id');
        $roomId = $request->input('room_id') ?? session('room_id');

        if (! $playerId || ! $roomId) {
            return redirect()->route('home');
        }

        try {
            $this->gameService->rematch($roomId, $playerId);
        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'error' => $e->getMessage(),
            ]);
        }

        return redirect()->route('room.show', $code);
    }

    public function sendChat(Request $request, string $code)
    {
        $playerId = $request->input('player_id') ?? session('player_id');
        $roomId = $request->input('room_id') ?? session('room_id');

        $validated = $request->validate([
            'message' => 'required|string|max:500',
        ]);

        if (! $playerId || ! $roomId) {
            throw ValidationException::withMessages([
                'error' => __('errors.player_not_in_room'),
            ]);
        }

        $player = Player::where('id', $playerId)->where('room_id', $roomId)->first();

        if (! $player) {
            throw ValidationException::withMessages([
                'error' => __('errors.player_not_in_room'),
            ]);
        }

        $chatMessage = ChatMessage::create([
            'room_id' => $roomId,
            'player_id' => $playerId,
            'message' => trim($validated['message']),
        ]);

        broadcast(new GameEvent($roomId, 'chat_message', [
            'id' => $chatMessage->id,
            'player' => [
                'id' => $player->id,
                'nickname' => $player->nickname,
                'avatar' => $player->avatar,
            ],
            'message' => $chatMessage->message,
            'created_at' => $chatMessage->created_at->toISOString(),
        ]));

        return back();
    }

    /**
     * Attempt to gracefully rejoin a player whose session references a purged player.
     * Called automatically when getGameState() fails for a known session.
     * Re-links session to the correct player or creates a new one.
     *
     * @return bool Whether reconnection was successful.
     */
    private function attemptGracefulRejoin(int $roomId, int $originalPlayerId): bool
    {
        $room = Room::find($roomId);

        if (! $room) {
            return false;
        }

        // Only attempt rejoin during active game states
        if (! in_array($room->status, ['playing', 'voting', 'round_result'])) {
            return false;
        }

        // Check if player still exists (race condition guard)
        $existingPlayer = Player::where('id', $originalPlayerId)->where('room_id', $roomId)->first();
        if ($existingPlayer) {
            // Player still exists, just refresh heartbeat
            $existingPlayer->update(['last_heartbeat_at' => now()]);

            return true;
        }

        // Player was purged. Try to find a re-created player (from a previous reconnect attempt)
        $rejoinedPlayer = Player::where('room_id', $roomId)
            ->where('session_id', 'rejoin:'.$originalPlayerId)
            ->first();

        if ($rejoinedPlayer) {
            // Re-link session to this re-created player
            session(['player_id' => $rejoinedPlayer->id]);
            $rejoinedPlayer->update(['last_heartbeat_at' => now()]);

            return true;
        }

        // Check room capacity before creating a new player
        $spectatorCount = $room->players()->where('is_spectator', true)->count();
        if ($room->players()->count() >= $room->max_players && $spectatorCount >= max(5, $room->max_players)) {
            return false;
        }

        // Determine if returning mid-round (spectator) or between rounds (player)
        $isDuringActiveRound = in_array($room->status, ['playing', 'voting']);

        // Create a new player to rejoin
        $originalNickname = session('player_nickname', 'Player');
        $originalAvatar = session('player_avatar');

        $newPlayer = Player::create([
            'nickname' => $originalNickname,
            'room_id' => $roomId,
            'is_ready' => ! $isDuringActiveRound,
            'is_imposter' => false,
            'is_spectator' => $isDuringActiveRound,
            'score' => 0,
            'avatar' => $originalAvatar,
            'session_id' => 'rejoin:'.$originalPlayerId,
        ]);

        session([
            'player_id' => $newPlayer->id,
            'player_nickname' => $originalNickname,
        ]);

        $newPlayer->update(['last_heartbeat_at' => now()]);

        return true;
    }

    /**
     * Redirect to the correct page based on room status.
     */
    private function redirectByRoomStatus(Room $room, string $code)
    {
        return match ($room->status) {
            'playing' => redirect()->route('game.show', $code),
            'voting' => redirect()->route('vote.show', $code),
            'round_result', 'finished' => redirect()->route('result.show', $code),
            default => redirect()->route('room.show', $code),
        };
    }
}
