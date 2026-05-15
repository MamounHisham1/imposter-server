<?php

namespace App\Http\Controllers;

use App\Services\GameService;
use Inertia\Inertia;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    public function __construct(
        private GameService $gameService
    ) {}

    public function index()
    {
        $rooms = $this->gameService->getPublicRooms();
        return Inertia::render('Home', [
            'rooms' => $rooms,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nickname' => 'required|string|max:20',
            'type' => 'required|in:public,private',
            'max_players' => 'required|integer|min:3|max:10',
            'rounds_per_game' => 'required|integer|min:1|max:5',
            'language' => 'required|in:en,ar',
        ]);

        try {
            $result = $this->gameService->createRoom(
                $validated['nickname'],
                $validated['type'],
                $validated['max_players'],
                $validated['rounds_per_game'],
                $validated['language']
            );
        } catch (\Exception $e) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'error' => $e->getMessage(),
            ]);
        }

        session([
            'player_id' => $result['player']['id'],
            'room_id' => $result['room']['id'],
            'locale' => $validated['language'],
        ]);

        return redirect()->route('room.show', $result['room']['code']);
    }

    public function join(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|size:6',
            'nickname' => 'required|string|max:20',
        ]);

        try {
            $result = $this->gameService->joinRoom(
                strtoupper($validated['code']),
                $validated['nickname']
            );
        } catch (\Exception $e) {
            // Return a 422 validation error directly instead of back()->withErrors().
            // back()->withErrors() triggers a 302 redirect, and during that redirect
            // Inertia can detect a Vite asset version mismatch (409 Conflict),
            // causing a full page reload that swallows the onError callback.
            throw \Illuminate\Validation\ValidationException::withMessages([
                'error' => $e->getMessage(),
            ]);
        }

        session([
            'player_id' => $result['player']['id'],
            'room_id' => $result['room']['id'],
            'locale' => $result['room']['language'],
        ]);

        return redirect()->route('room.show', $result['room']['code']);
    }

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

        if ($state['room']['status'] === 'waiting' || $state['room']['status'] === 'ready') {
            return Inertia::render('Room', $state);
        }

        return redirect()->route('game.show', $code);
    }

    public function toggleReady(Request $request)
    {
        $playerId = $request->input('player_id') ?? session('player_id');
        if (!$playerId) {
            return redirect()->route('home');
        }

        try {
            $this->gameService->toggleReady($playerId);
        } catch (\Exception $e) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'error' => $e->getMessage(),
            ]);
        }

        return back();
    }

    public function startGame(Request $request, string $code)
    {
        $playerId = $request->input('player_id') ?? session('player_id');
        $roomId = $request->input('room_id') ?? session('room_id');
        if (!$playerId || !$roomId) {
            return redirect()->route('home');
        }

        try {
            $this->gameService->startGame($roomId);
        } catch (\Exception $e) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'error' => $e->getMessage(),
            ]);
        }

        return redirect()->route('game.show', $code);
    }

    public function leaveRoom(Request $request)
    {
        $playerId = $request->input('player_id') ?? session('player_id');
        if (!$playerId) {
            return redirect()->route('home');
        }

        try {
            $this->gameService->leaveRoom($playerId);
        } catch (\Exception $e) {
            return redirect()->route('home');
        }

        if (session('player_id') == $playerId) {
            session()->forget(['player_id', 'room_id']);
        }

        return redirect()->route('home');
    }
}
