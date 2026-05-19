<?php

namespace App\Http\Controllers;

use App\Services\GameService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

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
            'category' => 'nullable|string|in:animals,food,places,technology,sports,nature,professions,music,vehicles',
            'difficulty' => 'nullable|string|in:easy,medium,hard',
            'avatar' => 'nullable|array',
            'avatar.head' => 'nullable|string',
            'avatar.eyes' => 'nullable|string',
            'avatar.hair' => 'nullable|string',
            'avatar.beard' => 'nullable|string',
        ]);

        try {
            $result = $this->gameService->createRoom(
                $validated['nickname'],
                $validated['type'],
                $validated['max_players'],
                $validated['rounds_per_game'],
                $validated['language'],
                $validated['avatar'] ?? null,
                Auth::id(),
                $validated['category'] ?? null,
                $validated['difficulty'] ?? 'medium'
            );
        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'error' => $e->getMessage(),
            ]);
        }

        session([
            'player_id' => $result['player']['id'],
            'room_id' => $result['room']['id'],
            'locale' => $validated['language'],
            'player_nickname' => $validated['nickname'],
            'player_avatar' => $validated['avatar'] ?? null,
        ]);

        return redirect()->route('room.show', $result['room']['code']);
    }

    public function join(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|size:6',
            'nickname' => 'required|string|max:20',
            'avatar' => 'nullable|array',
            'avatar.head' => 'nullable|string',
            'avatar.eyes' => 'nullable|string',
            'avatar.hair' => 'nullable|string',
            'avatar.beard' => 'nullable|string',
        ]);

        try {
            $result = $this->gameService->joinRoom(
                strtoupper($validated['code']),
                $validated['nickname'],
                $validated['avatar'] ?? null,
                Auth::id()
            );
        } catch (\Exception $e) {
            // Return a 422 validation error directly instead of back()->withErrors().
            // back()->withErrors() triggers a 302 redirect, and during that redirect
            // Inertia can detect a Vite asset version mismatch (409 Conflict),
            // causing a full page reload that swallows the onError callback.
            throw ValidationException::withMessages([
                'error' => $e->getMessage(),
            ]);
        }

        session([
            'player_id' => $result['player']['id'],
            'room_id' => $result['room']['id'],
            'locale' => $result['room']['language'],
            'player_nickname' => $validated['nickname'],
            'player_avatar' => $validated['avatar'] ?? null,
        ]);

        return redirect()->route('room.show', $result['room']['code']);
    }

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
            // Player record gone (stale cleanup, etc.) — try to reconnect
            $room = \App\Models\Room::where('code', strtoupper($code))->first();
            if ($room && $room->id == $roomId && in_array($room->status, ['playing', 'voting', 'round_result'])) {
                $nickname = session('player_nickname');
                $avatar = session('player_avatar');
                if ($nickname) {
                    try {
                        $result = $this->gameService->reconnectPlayer(
                            $roomId,
                            $nickname,
                            $avatar,
                            Auth::id()
                        );
                        session([
                            'player_id' => $result['player']['id'],
                            'room_id' => $result['room']['id'],
                        ]);
                        $state = $this->gameService->getGameState(
                            $result['room']['id'],
                            $result['player']['id']
                        );
                    } catch (\Exception $re) {
                        session()->forget(['player_id', 'room_id']);

                        return redirect()->route('home')->withErrors(['error' => $re->getMessage()]);
                    }
                } else {
                    session()->forget(['player_id', 'room_id']);

                    return redirect()->route('home');
                }
            } else {
                session()->forget(['player_id', 'room_id']);

                return redirect()->route('home')->withErrors(['error' => $e->getMessage()]);
            }
        }

        if ($state['room']['status'] === 'waiting' || $state['room']['status'] === 'ready') {
            return Inertia::render('Room', $state);
        }

        return redirect()->route('game.show', $code);
    }

    public function toggleReady(Request $request)
    {
        $playerId = $request->input('player_id') ?? session('player_id');
        if (! $playerId) {
            return redirect()->route('home');
        }

        try {
            $this->gameService->toggleReady($playerId);
        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'error' => $e->getMessage(),
            ]);
        }

        return back();
    }

    public function startGame(Request $request, string $code)
    {
        $playerId = $request->input('player_id') ?? session('player_id');
        $roomId = $request->input('room_id') ?? session('room_id');
        if (! $playerId || ! $roomId) {
            return redirect()->route('home');
        }

        try {
            $this->gameService->startGame($roomId);
        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'error' => $e->getMessage(),
            ]);
        }

        return redirect()->route('game.show', $code);
    }

    public function kickPlayer(Request $request, string $code)
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
            $this->gameService->kickPlayer($roomId, $playerId, $validated['target_id']);
        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'error' => $e->getMessage(),
            ]);
        }

        return back();
    }

    public function leaveRoom(Request $request)
    {
        $playerId = $request->input('player_id') ?? session('player_id');
        if (! $playerId) {
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
