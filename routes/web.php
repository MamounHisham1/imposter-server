<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CreditController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\WellKnownController;
use App\Models\Player;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Agent discovery (.well-known)
Route::get('/.well-known/api-catalog', [WellKnownController::class, 'apiCatalog'])->name('well-known.api-catalog');
Route::get('/.well-known/agent-skills/index.json', [WellKnownController::class, 'agentSkillsIndex'])->name('well-known.agent-skills');

// Dynamic sitemap
Route::get('/sitemap.xml', [WellKnownController::class, 'sitemap'])->name('sitemap');

// SEO content pages
Route::inertia('/how-to-play', 'HowToPlay')->name('how-to-play');
Route::inertia('/faq', 'Faq')->name('faq');
Route::inertia('/about', 'About')->name('about');

// Auth
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/auth/google', [SocialAuthController::class, 'redirect']);
Route::get('/auth/google/callback', [SocialAuthController::class, 'callback']);

Route::post('/locale', function (Request $request) {
    $locale = $request->validate(['locale' => 'required|in:en,ar'])['locale'];
    session(['locale' => $locale]);
    app()->setLocale($locale);

    return response()->noContent();
})->name('locale.set');

Route::get('/', [RoomController::class, 'index'])->name('home');
Route::inertia('/install', 'Install')->name('install');
Route::get('/stats', [StatsController::class, 'index'])->name('stats');

// Credits & Shop (auth required)
Route::middleware(['auth'])->group(function () {
    Route::get('/credits', [CreditController::class, 'index'])->name('credits');
    Route::get('/shop', [ShopController::class, 'index'])->name('shop');
    Route::post('/shop/buy/element', [ShopController::class, 'buyElement'])->name('shop.buy.element');
    Route::post('/shop/buy/costume', [ShopController::class, 'buyCostume'])->name('shop.buy.costume');
    Route::get('/api/inventory', [ShopController::class, 'inventory'])->name('api.inventory');
});

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
Route::post('/game/{code}/reconnect', [GameController::class, 'reconnect'])->name('game.reconnect');
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
Route::post('/game/{code}/rematch', [GameController::class, 'rematch'])->name('game.rematch');
Route::post('/game/{code}/chat', [GameController::class, 'sendChat'])->name('game.chat');
