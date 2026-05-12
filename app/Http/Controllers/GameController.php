<?php

namespace App\Http\Controllers;

use App\Services\GameService;
use Inertia\Inertia;
use Illuminate\Http\Request;

class GameController extends Controller
{
    public function __construct(
        private GameService $gameService
    ) {}

    public function show(string $code)
    {
        $roomId = session('room_id');
        $playerId = session('player_id');

        if (!$roomId || !$playerId) {
            return redirect()->route('home');
        }

        try {
            $state = $this->gameService->getGameState($roomId, $playerId);
        } catch (\Exception $e) {
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

    public function submitHint(Request $request, string $code)
    {
        $playerId = $request->input('player_id') ?? session('player_id');
        $roomId = $request->input('room_id') ?? session('room_id');

        $validated = $request->validate([
            'content' => 'required|string|max:100',
        ]);

        if (!$playerId || !$roomId) {
            return redirect()->route('home');
        }

        try {
            $state = $this->gameService->getGameState($roomId, $playerId);
        } catch (\Exception $e) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'error' => $e->getMessage(),
            ]);
        }

        $roundId = $state['current_round']['id'] ?? null;

        if (!$roundId) {
            throw \Illuminate\Validation\ValidationException::withMessages([
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
            throw \Illuminate\Validation\ValidationException::withMessages([
                'error' => $e->getMessage(),
            ]);
        }

        return back();
    }

    public function nextRound(Request $request, string $code)
    {
        $playerId = $request->input('player_id') ?? session('player_id');
        $roomId = $request->input('room_id') ?? session('room_id');

        if (!$playerId || !$roomId) {
            return redirect()->route('home');
        }

        try {
            $this->gameService->advanceRound($roomId, $playerId);
        } catch (\Exception $e) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'error' => $e->getMessage(),
            ]);
        }

        return back();
    }

    public function startVoting(Request $request, string $code)
    {
        $playerId = $request->input('player_id') ?? session('player_id');
        $roomId = $request->input('room_id') ?? session('room_id');

        if (!$playerId || !$roomId) {
            return redirect()->route('home');
        }

        try {
            $this->gameService->startVoting($roomId, $playerId);
        } catch (\Exception $e) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'error' => $e->getMessage(),
            ]);
        }

        return back();
    }

    public function vote(string $code)
    {
        $roomId = session('room_id');
        $playerId = session('player_id');

        if (!$roomId || !$playerId) {
            return redirect()->route('home');
        }

        try {
            $state = $this->gameService->getGameState($roomId, $playerId);
        } catch (\Exception $e) {
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

        if (!$playerId || !$roomId) {
            return redirect()->route('home');
        }

        try {
            $state = $this->gameService->getGameState($roomId, $playerId);
        } catch (\Exception $e) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'error' => $e->getMessage(),
            ]);
        }

        $roundId = $state['current_round']['id'] ?? null;

        if (!$roundId) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'error' => __('errors.no_active_round'),
            ]);
        }

        try {
            $this->gameService->submitVote($roundId, $playerId, $validated['target_id']);
        } catch (\Exception $e) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'error' => $e->getMessage(),
            ]);
        }

        return back();
    }

    public function result(string $code)
    {
        $roomId = session('room_id');
        $playerId = session('player_id');

        if (!$roomId || !$playerId) {
            return redirect()->route('home');
        }

        try {
            $state = $this->gameService->getGameState($roomId, $playerId);
        } catch (\Exception $e) {
            session()->forget(['player_id', 'room_id']);
            return redirect()->route('home')->withErrors(['error' => $e->getMessage()]);
        }

        return Inertia::render('Result', $state);
    }

    public function nextRoundFromResult(Request $request, string $code)
    {
        $playerId = $request->input('player_id') ?? session('player_id');
        $roomId = $request->input('room_id') ?? session('room_id');

        if (!$playerId || !$roomId) {
            return redirect()->route('home');
        }

        try {
            $this->gameService->advanceToNextRound($roomId, $playerId);
        } catch (\Exception $e) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'error' => $e->getMessage(),
            ]);
        }

        return redirect()->route('game.show', $code);
    }
}
