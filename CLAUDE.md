# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Imposter is a real-time multiplayer social deduction word game. One player is secretly the "imposter" who receives a vague hint instead of the real word. Players submit one-word clues, then vote to identify the imposter. Built with Laravel 13, Vue 3 + Inertia.js, and Laravel Reverb WebSockets.

## Commands

```bash
composer dev          # Run all services (PHP server, queue, scheduler, pail logs, Vite)
composer test         # Run PHPUnit tests (uses in-memory SQLite)
php artisan test      # Run tests directly
php artisan test --filter=TestName  # Run a single test
npm run dev           # Vite dev server only
npm run build         # Production frontend build
composer pint         # Lint/fix PHP code style
php artisan tinker    # Interactive REPL
```

Tests use in-memory SQLite (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`) per `phpunit.xml`.

## Architecture

### Backend (Laravel 13)

**Controllers** are thin — they validate input, call `GameService`, and return Inertia responses or redirects. All game logic lives in services.

- `RoomController` — room CRUD (create, join, leave, ready, start)
- `GameController` — game flow (show game, submit hint, start voting, submit vote, results, next round)

**Services** contain all business logic:

- `GameService` — core game engine: room management, hint submission with turn order enforcement, voting, score calculation, round advancement, mid-game player departure handling (imposter fled, game abort, hint/vote adjustment). Uses DB transactions for state changes and broadcasts WebSocket events after each action.
- `AiWordService` — generates word/hint pairs via `laravel/ai` agent() with structured output. Falls back to hardcoded `FALLBACK_WORDS` array when AI is unavailable. Supports batch generation (all rounds at game start) and single-word generation. Bilingual prompts for English and Arabic.
- `RoomCleanupService` — removes stale players (no heartbeat for 60s), deletes inactive rooms based on status-specific timeouts, handles mid-game cleanup by delegating to `GameService::leaveRoom`.

**Events** (broadcast via Reverb):

- `GameEvent` — per-room events on `room.{id}` channel (game_started, hint_submitted, hints_complete, voting_started, vote_submitted, round_result, next_round, game_over, player_left, etc.)
- `RoomListEvent` — public room list updates on a public channel

**Models**: Room, Player, Round, Hint, Vote, GameStat. Room has `word_pool` (JSON column) pre-generated at game start for all rounds. Round stores `hint_order` (JSON array of player IDs for turn order) and `vote_tally`.

**Scheduled**: `app:clean-inactive-rooms` runs every minute via `console.php`.

### Frontend (Vue 3 + Inertia.js + Tailwind CSS v4)

- `resources/js/app.js` — bootstraps Inertia, vue-i18n (en/ar), Laravel Echo (Reverb), global error toast handler, service worker registration
- `resources/js/Pages/` — Inertia pages: Home, Room (lobby), Game (hint submission), Vote, Result (round/game results), Install
- `resources/js/layouts/GameLayout.vue` — shared game layout
- `resources/js/i18n/` — translation JSON files (en.json, ar.json)
- `resources/css/app.css` — Tailwind entry point

### Key Design Patterns

- **No authentication** — players join with nicknames, tracked via session (`player_id`, `room_id`)
- **Session-based state** — controllers read `player_id`/`room_id` from session; game state is fetched fresh on each page load via `GameService::getGameState()`
- **WebSocket-first updates** — controllers broadcast events after mutations; frontend listens via Echo and updates reactively
- **Pre-generated word pool** — at game start, all word/hint pairs for all rounds are generated in one AI call and stored in `room.word_pool`. Subsequent rounds pull from the pool rather than making additional AI calls
- **Turn-ordered hints** — `round.hint_order` (shuffled player IDs) enforces sequential hint submission; only the current player in order can submit
- **Heartbeat system** — frontend sends periodic POST `/heartbeat`; stale players (60s no heartbeat) are auto-removed by the cleanup service
- **Creator-only actions** — starting the game, advancing rounds, and starting voting are restricted to the room creator (`creator_id`)
