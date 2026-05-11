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
            'max_players' => 'required|integer|min:3|max:8',
            'rounds_per_game' => 'required|integer|min:1|max:5',
        ]);

        $result = $this->gameService->createRoom(
            $validated['nickname'],
            $validated['type'],
            $validated['max_players'],
            $validated['rounds_per_game']
        );

        session([
            'player_id' => $result['player']['id'],
            'room_id' => $result['room']['id'],
        ]);

        return redirect()->route('room.show', $result['room']['code']);
    }

    public function join(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|size:6',
            'nickname' => 'required|string|max:20',
        ]);

        $result = $this->gameService->joinRoom(
            strtoupper($validated['code']),
            $validated['nickname']
        );

        session([
            'player_id' => $result['player']['id'],
            'room_id' => $result['room']['id'],
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

        $state = $this->gameService->getGameState($roomId, $playerId);

        if ($state['room']['status'] === 'waiting' || $state['room']['status'] === 'ready') {
            return Inertia::render('Room', $state);
        }

        return redirect()->route('game.show', $code);
    }

    public function toggleReady(Request $request)
    {
        $playerId = session('player_id');
        if (!$playerId) {
            return redirect()->route('home');
        }

        $result = $this->gameService->toggleReady($playerId);
        return back();
    }

    public function startGame(Request $request, string $code)
    {
        $roomId = session('room_id');
        if (!$roomId) {
            return redirect()->route('home');
        }

        $this->gameService->startGame($roomId);
        return redirect()->route('game.show', $code);
    }
}
