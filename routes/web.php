<?php

use App\Http\Controllers\GameController;
use App\Http\Controllers\RoomController;
use App\Models\Player;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/locale', function (Request $request) {
    $locale = $request->validate(['locale' => 'required|in:en,ar'])['locale'];
    session(['locale' => $locale]);
    app()->setLocale($locale);

    return response()->noContent();
})->name('locale.set');

Route::get('/', [RoomController::class, 'index'])->name('home');
Route::inertia('/install', 'Install')->name('install');

Route::post('/room', [RoomController::class, 'store'])->name('room.store');
Route::post('/room/join', [RoomController::class, 'join'])->name('room.join');
Route::get('/room/{code}', [RoomController::class, 'show'])->name('room.show');
Route::post('/room/{code}/ready', [RoomController::class, 'toggleReady'])->name('room.ready');
Route::post('/room/{code}/start', [RoomController::class, 'startGame'])->name('room.start');
Route::post('/room/{code}/leave', [RoomController::class, 'leaveRoom'])->name('room.leave');
Route::post('/room/{code}/kick', [RoomController::class, 'kickPlayer'])->name('room.kick');

Route::post('/heartbeat', function (Request $request) {
    $request->validate(['player_id' => 'required|integer']);

    Player::where('id', $request->player_id)->update(['last_heartbeat_at' => now()]);

    return response()->noContent();
});

Route::get('/game/{code}', [GameController::class, 'show'])->name('game.show');
Route::post('/game/{code}/hint', [GameController::class, 'submitHint'])->name('game.hint');
Route::post('/game/{code}/skip-hint', [GameController::class, 'skipHint'])->name('game.skip-hint');
Route::post('/game/{code}/next-round', [GameController::class, 'nextRound'])->name('game.next-round');
Route::post('/game/{code}/start-voting', [GameController::class, 'startVoting'])->name('game.start-voting');
Route::post('/game/{code}/phase-vote', [GameController::class, 'phaseVote'])->name('game.phase-vote');
Route::get('/game/{code}/vote', [GameController::class, 'vote'])->name('vote.show');
Route::post('/game/{code}/vote', [GameController::class, 'submitVote'])->name('game.vote');
Route::post('/game/{code}/timeout-vote', [GameController::class, 'timeoutVote'])->name('game.timeout-vote');
Route::get('/game/{code}/result', [GameController::class, 'result'])->name('result.show');
Route::post('/game/{code}/next-round-result', [GameController::class, 'nextRoundFromResult'])->name('game.next-round-result');
