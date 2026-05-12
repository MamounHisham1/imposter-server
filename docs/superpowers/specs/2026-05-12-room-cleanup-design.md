# Room Cleanup System Redesign

## Problem

The room cleanup logic is duplicated across three locations (`CleanInactiveRooms` command, `GameService::cleanAllStale()`, `GameService::purgeStalePlayers()`) with inconsistent behavior, missing broadcasts, race conditions, and incomplete status coverage.

## Bugs Fixed

1. `cleanAllStale()` does not broadcast `GameEvent` for `room_deleted` or `player_left` — players in a room are not notified when cleanup happens during a homepage load
2. `removeInactiveRooms()` does a silent bulk delete with zero WebSocket events — players get no notification
3. `round_result` status is missing from inactivity thresholds — rooms stuck in this state are never cleaned
4. `removeStalePlayers()` iterates stale players one-by-one — if two stale players share a room, the second iteration fails because cascade already deleted the player row
5. No transaction wrapping — concurrent scheduler runs can conflict
6. Game.vue, Vote.vue, Result.vue do not handle the `room_deleted` WebSocket event

## Architecture

Extract a `RoomCleanupService` that owns all cleanup logic. Both `CleanInactiveRooms` and `GameService` delegate to it.

```
RoomCleanupService (new, app/Services/RoomCleanupService.php)
├── purgeStalePlayers(Room $room, bool $broadcastGameEvents): CleanupResult
├── purgeStalePlayersFromAllRooms(bool $broadcastGameEvents): void
├── removeInactiveRooms(): int
└── removePlayersFromRoom(Room $room, array $playerIds, bool $broadcastGameEvents): CleanupResult  (private)
```

- `broadcastGameEvents = true` — scheduler command, join, game-state-fetch (notify active players via WebSocket)
- `broadcastGameEvents = false` — homepage load (only RoomListEvent for public room list)

### CleanupResult

```php
readonly class CleanupResult {
    public function __construct(
        public bool $roomDeleted = false,
        public bool $creatorChanged = false,
        public int $removedPlayerCount = 0,
        public ?int $newCreatorId = null,
    ) {}
}
```

## Behavior

### Stale Player Detection

- `last_heartbeat_at < now() - 60s` OR `last_heartbeat_at IS NULL`
- AND `created_at < now() - 60s` (grace period for new players)

### Room Inactivity Thresholds

| Status     | Threshold   |
|------------|-------------|
| waiting    | 30 minutes  |
| finished   | 10 minutes  |
| playing    | 60 minutes  |
| voting     | 60 minutes  |
| round_result | 60 minutes |

### Broadcast Rules

| Action                            | RoomListEvent              | GameEvent                        |
|-----------------------------------|----------------------------|----------------------------------|
| Player removed, room survives     | `updated` (public+waiting) | `player_left` (if broadcastGE)   |
| Creator transferred               | —                          | `creator_changed` (if broadcastGE) |
| Room deleted (empty players)      | `removed` (if public)      | `room_deleted` (if broadcastGE)  |
| Room deleted (inactive timeout)   | `removed` (if public)      | `room_deleted`                   |

### Race Condition Fix

Instead of iterating stale players one-by-one, group them by room. For each room, delete all stale players in a single query, then check remaining count. Wrap per-room in a DB transaction.

### Frontend Fix

Add `room_deleted` handler to Game.vue, Vote.vue, and Result.vue that redirects to `/`.

## Files Changed

- **New:** `app/Services/RoomCleanupService.php` — consolidated cleanup logic
- **New:** `app/DTOs/CleanupResult.php` — return value object
- **Modified:** `app/Console/Commands/CleanInactiveRooms.php` — thin wrapper calling RoomCleanupService
- **Modified:** `app/Services/GameService.php` — remove `cleanAllStale()` and `purgeStalePlayers()`, call RoomCleanupService instead
- **Modified:** `resources/js/Pages/Game.vue` — add `room_deleted` handler
- **Modified:** `resources/js/Pages/Vote.vue` — add `room_deleted` handler
- **Modified:** `resources/js/Pages/Result.vue` — add `room_deleted` handler
