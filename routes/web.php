<?php

use App\Http\Controllers\RoomController;
use App\Http\Controllers\GameController;
use Illuminate\Support\Facades\Route;

Route::get('/', [RoomController::class, 'index'])->name('home');

Route::post('/room', [RoomController::class, 'store'])->name('room.store');
Route::post('/room/join', [RoomController::class, 'join'])->name('room.join');
Route::get('/room/{code}', [RoomController::class, 'show'])->name('room.show');
Route::post('/room/{code}/ready', [RoomController::class, 'toggleReady'])->name('room.ready');
Route::post('/room/{code}/start', [RoomController::class, 'startGame'])->name('room.start');

Route::get('/game/{code}', [GameController::class, 'show'])->name('game.show');
Route::post('/game/{code}/hint', [GameController::class, 'submitHint'])->name('game.hint');
Route::get('/game/{code}/vote', [GameController::class, 'vote'])->name('vote.show');
Route::post('/game/{code}/vote', [GameController::class, 'submitVote'])->name('game.vote');
Route::get('/game/{code}/result', [GameController::class, 'result'])->name('result.show');
