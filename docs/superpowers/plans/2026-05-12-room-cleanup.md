# Room Cleanup System Redesign — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Consolidate three duplicated cleanup implementations into a single `RoomCleanupService`, fix all 6 bugs, and add missing `room_deleted` frontend handling.

**Architecture:** New `RoomCleanupService` owns all cleanup logic (stale player removal + inactive room deletion). `CleanInactiveRooms` command and `GameService` become thin callers. A `CleanupResult` DTO communicates what happened. Frontend Vue pages get the missing `room_deleted` event handler.

**Tech Stack:** Laravel 13, PHP 8.3, PHPUnit 12, Vue 3, SQLite (in-memory tests)

---

### Task 1: Create the `CleanupResult` DTO

**Files:**
- Create: `app/DTOs/CleanupResult.php`

- [ ] **Step 1: Create the DTO file**

```php
<?php

namespace App\DTOs;

readonly class CleanupResult
{
    public function __construct(
        public bool $roomDeleted = false,
        public bool $creatorChanged = false,
        public int $removedPlayerCount = 0,
        public ?int $newCreatorId = null,
    ) {}
}
```

- [ ] **Step 2: Verify the file loads with no syntax errors**

Run: `cd /home/mamoun/games/imposter/backend && php -l app/DTOs/CleanupResult.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add app/DTOs/CleanupResult.php
git commit -m "feat: add CleanupResult DTO for room cleanup operations"
```

---

### Task 2: Create `RoomCleanupService` with `removePlayersFromRoom`

**Files:**
- Create: `app/Services/RoomCleanupService.php`

- [ ] **Step 1: Write the service with the private `removePlayersFromRoom` method**

```php
<?php

namespace App\Services;

use App\DTOs\CleanupResult;
use App\Events\GameEvent;
use App\Events\RoomListEvent;
use App\Models\Player;
use App\Models\Room;
use Illuminate\Support\Facades\DB;

class RoomCleanupService
{
    private function removePlayersFromRoom(Room $room, array $playerIds, bool $broadcastGameEvents): CleanupResult
    {
        if (empty($playerIds)) {
            return new CleanupResult();
        }

        $wasCreator = $room->players()
            ->whereIn('id', $playerIds)
            ->where('id', $room->creator_id)
            ->exists();

        Player::whereIn('id', $playerIds)->delete();

        $remainingCount = $room->fresh()->players()->count();

        if ($remainingCount === 0) {
            $room->delete();

            if ($room->type === 'public') {
                broadcast(new RoomListEvent('removed', ['id' => $room->id, 'code' => $room->code]));
            }

            if ($broadcastGameEvents) {
                broadcast(new GameEvent($room->id, 'room_deleted', ['code' => $room->code]));
            }

            return new CleanupResult(
                roomDeleted: true,
                removedPlayerCount: count($playerIds),
            );
        }

        $result = new CleanupResult(
            removedPlayerCount: count($playerIds),
        );

        if ($wasCreator) {
            $newCreator = $room->players()->orderBy('id')->first();
            $room->update(['creator_id' => $newCreator->id]);

            if ($broadcastGameEvents) {
                broadcast(new GameEvent($room->id, 'creator_changed', [
                    'new_creator_id' => $newCreator->id,
                ]));
            }

            $result = new CleanupResult(
                creatorChanged: true,
                removedPlayerCount: count($playerIds),
                newCreatorId: $newCreator->id,
            );
        }

        if ($broadcastGameEvents) {
            foreach ($playerIds as $pid) {
                broadcast(new GameEvent($room->id, 'player_left', ['player_id' => $pid]));
            }
        }

        if ($room->type === 'public' && $room->status === 'waiting') {
            broadcast(new RoomListEvent('updated', [
                'id' => $room->id,
                'code' => $room->code,
                'players_count' => $remainingCount,
                'max_players' => $room->max_players,
            ]));
        }

        return $result;
    }
}
```

- [ ] **Step 2: Verify syntax**

Run: `cd /home/mamoun/games/imposter/backend && php -l app/Services/RoomCleanupService.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add app/Services/RoomCleanupService.php
git commit -m "feat: add RoomCleanupService with removePlayersFromRoom"
```

---

### Task 3: Add `purgeStalePlayers` and `purgeStalePlayersFromAllRooms` to `RoomCleanupService`

**Files:**
- Modify: `app/Services/RoomCleanupService.php`

- [ ] **Step 1: Add the two public methods to the class (before the `removePlayersFromRoom` method)**

Insert these methods into `RoomCleanupService`, before the `removePlayersFromRoom` method:

```php
    public function purgeStalePlayers(Room $room, bool $broadcastGameEvents): CleanupResult
    {
        $staleThreshold = now()->subSeconds(60);

        $stalePlayerIds = $room->players()
            ->where(function ($q) use ($staleThreshold) {
                $q->where('last_heartbeat_at', '<', $staleThreshold)
                    ->orWhereNull('last_heartbeat_at');
            })
            ->where('created_at', '<', $staleThreshold)
            ->pluck('id')
            ->toArray();

        if (empty($stalePlayerIds)) {
            return new CleanupResult();
        }

        return DB::transaction(fn () => $this->removePlayersFromRoom($room, $stalePlayerIds, $broadcastGameEvents));
    }

    public function purgeStalePlayersFromAllRooms(bool $broadcastGameEvents): void
    {
        $staleThreshold = now()->subSeconds(60);

        $stalePlayerIds = Player::where(function ($q) use ($staleThreshold) {
            $q->where('last_heartbeat_at', '<', $staleThreshold)
                ->orWhereNull('last_heartbeat_at');
        })
            ->where('created_at', '<', $staleThreshold)
            ->pluck('id');

        if ($stalePlayerIds->isEmpty()) {
            return;
        }

        $affectedRoomIds = Player::whereIn('id', $stalePlayerIds)
            ->pluck('room_id')
            ->unique()
            ->filter();

        foreach ($affectedRoomIds as $roomId) {
            $room = Room::find($roomId);
            if (! $room) {
                continue;
            }

            $staleInRoom = Player::whereIn('id', $stalePlayerIds)
                ->where('room_id', $roomId)
                ->pluck('id')
                ->toArray();

            DB::transaction(fn () => $this->removePlayersFromRoom($room, $staleInRoom, $broadcastGameEvents));
        }
    }
```

- [ ] **Step 2: Verify syntax**

Run: `cd /home/mamoun/games/imposter/backend && php -l app/Services/RoomCleanupService.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add app/Services/RoomCleanupService.php
git commit -m "feat: add purgeStalePlayers and purgeStalePlayersFromAllRooms"
```

---

### Task 4: Add `removeInactiveRooms` to `RoomCleanupService`

**Files:**
- Modify: `app/Services/RoomCleanupService.php`

- [ ] **Step 1: Add the `removeInactiveRooms` method to the class (after `purgeStalePlayersFromAllRooms`, before `removePlayersFromRoom`)**

```php
    public function removeInactiveRooms(): int
    {
        $thresholds = [
            'waiting' => now()->subMinutes(30),
            'finished' => now()->subMinutes(10),
            'playing' => now()->subMinutes(60),
            'voting' => now()->subMinutes(60),
            'round_result' => now()->subMinutes(60),
        ];

        $totalDeleted = 0;

        foreach ($thresholds as $status => $threshold) {
            $rooms = Room::where('status', $status)
                ->where(function ($q) use ($threshold) {
                    $q->where('last_activity_at', '<', $threshold)
                        ->orWhereNull('last_activity_at');
                })
                ->get();

            foreach ($rooms as $room) {
                $roomId = $room->id;
                $roomCode = $room->code;
                $roomType = $room->type;

                $room->delete();

                if ($roomType === 'public') {
                    broadcast(new RoomListEvent('removed', ['id' => $roomId, 'code' => $roomCode]));
                }

                broadcast(new GameEvent($roomId, 'room_deleted', ['code' => $roomCode]));

                $totalDeleted++;
            }
        }

        return $totalDeleted;
    }
```

- [ ] **Step 2: Verify syntax**

Run: `cd /home/mamoun/games/imposter/backend && php -l app/Services/RoomCleanupService.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add app/Services/RoomCleanupService.php
git commit -m "feat: add removeInactiveRooms with round_result status and proper broadcasts"
```

---

### Task 5: Rewrite `CleanInactiveRooms` command to use `RoomCleanupService`

**Files:**
- Modify: `app/Console/Commands/CleanInactiveRooms.php`

- [ ] **Step 1: Replace the entire file with a thin wrapper**

```php
<?php

namespace App\Console\Commands;

use App\Services\RoomCleanupService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:clean-inactive-rooms')]
#[Description('Delete inactive rooms and remove disconnected players')]
class CleanInactiveRooms extends Command
{
    public function __construct(
        private RoomCleanupService $cleanupService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->cleanupService->purgeStalePlayersFromAllRooms(broadcastGameEvents: true);

        $deleted = $this->cleanupService->removeInactiveRooms();

        if ($deleted > 0) {
            $this->info("Deleted {$deleted} inactive room(s)");
        }

        return self::SUCCESS;
    }
}
```

- [ ] **Step 2: Verify syntax**

Run: `cd /home/mamoun/games/imposter/backend && php -l app/Console/Commands/CleanInactiveRooms.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add app/Console/Commands/CleanInactiveRooms.php
git commit -m "refactor: CleanInactiveRooms now delegates to RoomCleanupService"
```

---

### Task 6: Update `GameService` to use `RoomCleanupService`

**Files:**
- Modify: `app/Services/GameService.php`

- [ ] **Step 1: Add the `RoomCleanupService` dependency to the constructor**

Replace the constructor at line 18-20:

```php
    public function __construct(
        private AiWordService $aiWordService,
        private RoomCleanupService $cleanupService,
    ) {}
```

- [ ] **Step 2: Replace the `cleanAllStale()` method (lines 22-76) with a call to the new service**

Delete the entire `cleanAllStale()` method (lines 22-76) and replace with:

```php
    private function cleanAllStale(): void
    {
        $this->cleanupService->purgeStalePlayersFromAllRooms(broadcastGameEvents: false);
    }
```

- [ ] **Step 3: Replace the `purgeStalePlayers()` method (lines 78-136) with a call to the new service**

Delete the entire `purgeStalePlayers()` method (lines 78-136) and replace with:

```php
    private function purgeStalePlayers(Room $room): void
    {
        $this->cleanupService->purgeStalePlayers($room, broadcastGameEvents: true);
    }
```

- [ ] **Step 4: Remove unused imports that were only needed by the old methods**

Remove these imports from the top of `GameService.php` (they are no longer used directly in this file):
- `use Illuminate\Support\Facades\DB;` — still used by other methods, keep it
- Keep all other imports (`GameEvent`, `RoomListEvent`, `Hint`, `Player`, `Room`, `Round`, `Vote`, `Collection`, `Log`) — verify each is still used by searching the file. The only import that can be removed is none — all are still used by other methods.

Actually, verify: `use Illuminate\Database\Eloquent\Collection;` — check if `Collection` is still used. The `getPublicRooms` return type references `\Illuminate\Support\Collection`, not `Illuminate\Database\Eloquent\Collection`. Check the file for any use of the unqualified `Collection` type.

Run: `cd /home/mamoun/games/imposter/backend && grep -n 'Collection' app/Services/GameService.php`

If `Illuminate\Database\Eloquent\Collection` is not used anywhere in the file, remove that import.

- [ ] **Step 5: Verify syntax**

Run: `cd /home/mamoun/games/imposter/backend && php -l app/Services/GameService.php`
Expected: `No syntax errors detected`

- [ ] **Step 6: Commit**

```bash
git add app/Services/GameService.php
git commit -m "refactor: GameService cleanup methods delegate to RoomCleanupService"
```

---

### Task 7: Add `room_deleted` handler to `Game.vue`

**Files:**
- Modify: `resources/js/Pages/Game.vue`

- [ ] **Step 1: Add the `room_deleted` case to the switch statement**

In the switch statement inside the `.listen('.game.event', ...)` callback (around line 121-157), add a `room_deleted` case. Insert it as the first case in the switch (before `hint_submitted`) so it's caught regardless of game state:

```javascript
                    case 'room_deleted':
                        router.visit('/');
                        break;
```

The switch statement should now start like:
```javascript
                switch (e.type) {
                    case 'room_deleted':
                        router.visit('/');
                        break;
                    case 'hint_submitted':
                        // ... existing code
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/Pages/Game.vue
git commit -m "fix: add room_deleted handler to Game.vue"
```

---

### Task 8: Add `room_deleted` handler to `Vote.vue`

**Files:**
- Modify: `resources/js/Pages/Vote.vue`

- [ ] **Step 1: Add the `room_deleted` case to the switch statement**

In the switch statement inside the `.listen('.game.event', ...)` callback (around line 52-61), add the case as the first entry:

```javascript
                    case 'room_deleted':
                        router.visit('/');
                        break;
```

The switch statement should now start like:
```javascript
                switch (e.type) {
                    case 'room_deleted':
                        router.visit('/');
                        break;
                    case 'vote_submitted':
                        // ... existing code
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/Pages/Vote.vue
git commit -m "fix: add room_deleted handler to Vote.vue"
```

---

### Task 9: Add `room_deleted` handler to `Result.vue`

**Files:**
- Modify: `resources/js/Pages/Result.vue`

- [ ] **Step 1: Add the `room_deleted` case to the switch statement**

In the switch statement inside the `.listen('.game.event', ...)` callback (around line 61-68), add the case as the first entry:

```javascript
                    case 'room_deleted':
                        router.visit('/');
                        break;
```

The switch statement should now start like:
```javascript
                switch (e.type) {
                    case 'room_deleted':
                        router.visit('/');
                        break;
                    case 'next_round':
                        // ... existing code
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/Pages/Result.vue
git commit -m "fix: add room_deleted handler to Result.vue"
```

---

### Task 10: Write tests for `RoomCleanupService`

**Files:**
- Create: `tests/Feature/RoomCleanupTest.php`

- [ ] **Step 1: Write the test file**

```php
<?php

namespace Tests\Feature;

use App\DTOs\CleanupResult;
use App\Models\Player;
use App\Models\Room;
use App\Services\RoomCleanupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RoomCleanupTest extends TestCase
{
    use RefreshDatabase;

    private RoomCleanupService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(RoomCleanupService::class);
    }

    private function createRoomWithPlayers(string $status = 'waiting', int $count = 3, string $type = 'public'): array
    {
        $room = Room::create([
            'code' => 'ABC123',
            'type' => $type,
            'status' => $status,
            'max_players' => 8,
            'rounds_per_game' => 3,
        ]);

        $players = [];
        for ($i = 0; $i < $count; $i++) {
            $players[] = Player::create([
                'nickname' => "Player {$i}",
                'room_id' => $room->id,
            ]);
        }

        $room->update(['creator_id' => $players[0]->id]);

        return ['room' => $room->fresh(), 'players' => $players];
    }

    // --- purgeStalePlayers tests ---

    public function test_no_stale_players_returns_empty_result(): void
    {
        ['room' => $room] = $this->createRoomWithPlayers();

        // Fresh players have recent heartbeats (created_at is now)
        $result = $this->service->purgeStalePlayers($room, broadcastGameEvents: true);

        $this->assertFalse($result->roomDeleted);
        $this->assertEquals(0, $result->removedPlayerCount);
        $this->assertEquals(0, $room->fresh()->players()->count());
        $this->assertFalse($result->creatorChanged);
    }

    public function test_stale_player_without_broadcast_deletes_player(): void
    {
        ['room' => $room, 'players' => $players] = $this->createRoomWithPlayers(count: 4);

        // Make player 0 stale: old heartbeat and old created_at
        $players[0]->update([
            'last_heartbeat_at' => now()->subSeconds(120),
            'created_at' => now()->subSeconds(120),
        ]);

        Event::fake();

        $result = $this->service->purgeStalePlayers($room, broadcastGameEvents: false);

        $this->assertEquals(1, $result->removedPlayerCount);
        $this->assertFalse($result->roomDeleted);
        $this->assertEquals(3, $room->fresh()->players()->count());

        // GameEvent should NOT be dispatched
        Event::assertNotDispatched(\App\Events\GameEvent::class);
    }

    public function test_stale_player_with_broadcast_dispatches_events(): void
    {
        ['room' => $room, 'players' => $players] = $this->createRoomWithPlayers(count: 4);

        $players[0]->update([
            'last_heartbeat_at' => now()->subSeconds(120),
            'created_at' => now()->subSeconds(120),
        ]);

        Event::fake();

        $result = $this->service->purgeStalePlayers($room, broadcastGameEvents: true);

        $this->assertEquals(1, $result->removedPlayerCount);
        Event::assertDispatched(\App\Events\GameEvent::class, function ($event) use ($room) {
            return $event->roomId === $room->id && $event->data['type'] === 'player_left';
        });
    }

    public function test_all_stale_players_deletes_room(): void
    {
        ['room' => $room, 'players' => $players] = $this->createRoomWithPlayers(count: 2);

        foreach ($players as $p) {
            $p->update([
                'last_heartbeat_at' => now()->subSeconds(120),
                'created_at' => now()->subSeconds(120),
            ]);
        }

        Event::fake();

        $result = $this->service->purgeStalePlayers($room, broadcastGameEvents: true);

        $this->assertTrue($result->roomDeleted);
        $this->assertEquals(2, $result->removedPlayerCount);
        $this->assertNull(Room::find($room->id));
        Event::assertDispatched(\App\Events\GameEvent::class, function ($event) use ($room) {
            return $event->roomId === $room->id && $event->data['type'] === 'room_deleted';
        });
        Event::assertDispatched(\App\Events\RoomListEvent::class, function ($event) {
            return $event->action === 'removed';
        });
    }

    public function test_stale_creator_transfers_creator_role(): void
    {
        ['room' => $room, 'players' => $players] = $this->createRoomWithPlayers(count: 3);

        // Creator is players[0], make them stale
        $players[0]->update([
            'last_heartbeat_at' => now()->subSeconds(120),
            'created_at' => now()->subSeconds(120),
        ]);

        Event::fake();

        $result = $this->service->purgeStalePlayers($room, broadcastGameEvents: true);

        $this->assertTrue($result->creatorChanged);
        $this->assertEquals($players[1]->id, $result->newCreatorId);
        $this->assertEquals($players[1]->id, $room->fresh()->creator_id);
        Event::assertDispatched(\App\Events\GameEvent::class, function ($event) use ($room) {
            return $event->roomId === $room->id && $event->data['type'] === 'creator_changed';
        });
    }

    public function test_multiple_stale_players_in_same_room(): void
    {
        ['room' => $room, 'players' => $players] = $this->createRoomWithPlayers(count: 4);

        // Make 2 players stale
        $players[0]->update([
            'last_heartbeat_at' => now()->subSeconds(120),
            'created_at' => now()->subSeconds(120),
        ]);
        $players[1]->update([
            'last_heartbeat_at' => now()->subSeconds(120),
            'created_at' => now()->subSeconds(120),
        ]);

        Event::fake();

        $result = $this->service->purgeStalePlayers($room, broadcastGameEvents: true);

        $this->assertEquals(2, $result->removedPlayerCount);
        $this->assertFalse($result->roomDeleted);
        $this->assertEquals(2, $room->fresh()->players()->count());
    }

    public function test_private_room_no_roomlist_event_on_delete(): void
    {
        ['room' => $room, 'players' => $players] = $this->createRoomWithPlayers(count: 2, type: 'private');

        foreach ($players as $p) {
            $p->update([
                'last_heartbeat_at' => now()->subSeconds(120),
                'created_at' => now()->subSeconds(120),
            ]);
        }

        Event::fake();

        $result = $this->service->purgeStalePlayers($room, broadcastGameEvents: true);

        $this->assertTrue($result->roomDeleted);
        Event::assertNotDispatched(\App\Events\RoomListEvent::class);
    }

    public function test_new_player_grace_period_not_purged(): void
    {
        ['room' => $room, 'players' => $players] = $this->createRoomWithPlayers(count: 2);

        // Player has null heartbeat but was just created (within 60s grace)
        $players[0]->update([
            'last_heartbeat_at' => null,
            'created_at' => now()->subSeconds(10),
        ]);

        $result = $this->service->purgeStalePlayers($room, broadcastGameEvents: true);

        $this->assertEquals(0, $result->removedPlayerCount);
        $this->assertEquals(2, $room->fresh()->players()->count());
    }

    // --- purgeStalePlayersFromAllRooms tests ---

    public function test_purge_all_stale_across_multiple_rooms(): void
    {
        $room1 = $this->createRoomWithPlayers(count: 2);
        $room2 = $this->createRoomWithPlayers(count: 2);

        // Stale player in room1
        $room1['players'][0]->update([
            'last_heartbeat_at' => now()->subSeconds(120),
            'created_at' => now()->subSeconds(120),
        ]);

        // Stale player in room2
        $room2['players'][0]->update([
            'last_heartbeat_at' => now()->subSeconds(120),
            'created_at' => now()->subSeconds(120),
        ]);

        Event::fake();

        $this->service->purgeStalePlayersFromAllRooms(broadcastGameEvents: true);

        $this->assertEquals(1, $room1['room']->fresh()->players()->count());
        $this->assertEquals(1, $room2['room']->fresh()->players()->count());
    }

    public function test_purge_all_deletes_empty_rooms(): void
    {
        $data = $this->createRoomWithPlayers(count: 1);

        $data['players'][0]->update([
            'last_heartbeat_at' => now()->subSeconds(120),
            'created_at' => now()->subSeconds(120),
        ]);

        Event::fake();

        $this->service->purgeStalePlayersFromAllRooms(broadcastGameEvents: true);

        $this->assertNull(Room::find($data['room']->id));
    }

    // --- removeInactiveRooms tests ---

    public function test_waiting_room_deleted_after_30_minutes(): void
    {
        $room = Room::create([
            'code' => 'OLD001',
            'type' => 'public',
            'status' => 'waiting',
            'max_players' => 8,
            'rounds_per_game' => 3,
            'last_activity_at' => now()->subMinutes(31),
        ]);

        Event::fake();

        $deleted = $this->service->removeInactiveRooms();

        $this->assertEquals(1, $deleted);
        $this->assertNull(Room::find($room->id));
        Event::assertDispatched(\App\Events\GameEvent::class, function ($event) use ($room) {
            return $event->roomId === $room->id && $event->data['type'] === 'room_deleted';
        });
        Event::assertDispatched(\App\Events\RoomListEvent::class, function ($event) {
            return $event->action === 'removed';
        });
    }

    public function test_waiting_room_not_deleted_if_recently_active(): void
    {
        $room = Room::create([
            'code' => 'NEW001',
            'type' => 'public',
            'status' => 'waiting',
            'max_players' => 8,
            'rounds_per_game' => 3,
            'last_activity_at' => now()->subMinutes(5),
        ]);

        $deleted = $this->service->removeInactiveRooms();

        $this->assertEquals(0, $deleted);
        $this->assertNotNull(Room::find($room->id));
    }

    public function test_finished_room_deleted_after_10_minutes(): void
    {
        $room = Room::create([
            'code' => 'DONE01',
            'type' => 'public',
            'status' => 'finished',
            'max_players' => 8,
            'rounds_per_game' => 3,
            'last_activity_at' => now()->subMinutes(11),
        ]);

        $deleted = $this->service->removeInactiveRooms();

        $this->assertEquals(1, $deleted);
        $this->assertNull(Room::find($room->id));
    }

    public function test_playing_room_deleted_after_60_minutes(): void
    {
        $room = Room::create([
            'code' => 'PLAY01',
            'type' => 'public',
            'status' => 'playing',
            'max_players' => 8,
            'rounds_per_game' => 3,
            'last_activity_at' => now()->subMinutes(61),
        ]);

        $deleted = $this->service->removeInactiveRooms();

        $this->assertEquals(1, $deleted);
        $this->assertNull(Room::find($room->id));
    }

    public function test_round_result_room_deleted_after_60_minutes(): void
    {
        $room = Room::create([
            'code' => 'RSLT01',
            'type' => 'public',
            'status' => 'round_result',
            'max_players' => 8,
            'rounds_per_game' => 3,
            'last_activity_at' => now()->subMinutes(61),
        ]);

        $deleted = $this->service->removeInactiveRooms();

        $this->assertEquals(1, $deleted);
        $this->assertNull(Room::find($room->id));
    }

    public function test_voting_room_deleted_after_60_minutes(): void
    {
        $room = Room::create([
            'code' => 'VOTE01',
            'type' => 'public',
            'status' => 'voting',
            'max_players' => 8,
            'rounds_per_game' => 3,
            'last_activity_at' => now()->subMinutes(61),
        ]);

        $deleted = $this->service->removeInactiveRooms();

        $this->assertEquals(1, $deleted);
        $this->assertNull(Room::find($room->id));
    }

    public function test_null_last_activity_treated_as_inactive(): void
    {
        $room = Room::create([
            'code' => 'NULL01',
            'type' => 'public',
            'status' => 'finished',
            'max_players' => 8,
            'rounds_per_game' => 3,
            // last_activity_at defaults to now() via booted(), so set it null after creation
        ]);

        $room->update(['last_activity_at' => null]);

        $deleted = $this->service->removeInactiveRooms();

        $this->assertEquals(1, $deleted);
        $this->assertNull(Room::find($room->id));
    }

    public function test_inactive_private_room_deleted_without_roomlist_event(): void
    {
        $room = Room::create([
            'code' => 'PRIV01',
            'type' => 'private',
            'status' => 'finished',
            'max_players' => 8,
            'rounds_per_game' => 3,
            'last_activity_at' => now()->subMinutes(11),
        ]);

        Event::fake();

        $deleted = $this->service->removeInactiveRooms();

        $this->assertEquals(1, $deleted);
        $this->assertNull(Room::find($room->id));
        Event::assertNotDispatched(\App\Events\RoomListEvent::class);
        Event::assertDispatched(\App\Events\GameEvent::class);
    }
}
```

- [ ] **Step 2: Run the tests**

Run: `cd /home/mamoun/games/imposter/backend && php artisan test --filter=RoomCleanupTest`
Expected: All tests pass.

If any test fails, read the error message, fix the test or the service, and re-run until all pass.

- [ ] **Step 3: Run the full test suite to make sure nothing is broken**

Run: `cd /home/mamoun/games/imposter/backend && php artisan test`
Expected: All tests pass (including the existing ExampleTest).

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/RoomCleanupTest.php
git commit -m "test: comprehensive tests for RoomCleanupService"
```

---

### Task 11: Write test for `CleanInactiveRooms` command

**Files:**
- Create: `tests/Feature/CleanInactiveRoomsCommandTest.php`

- [ ] **Step 1: Write the command test**

```php
<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CleanInactiveRoomsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_deletes_stale_players_and_inactive_rooms(): void
    {
        Event::fake();

        // Room with a stale player (should be cleaned)
        $room = Room::create([
            'code' => 'STALE1',
            'type' => 'public',
            'status' => 'waiting',
            'max_players' => 8,
            'rounds_per_game' => 3,
        ]);
        $stalePlayer = Player::create([
            'nickname' => 'Ghost',
            'room_id' => $room->id,
            'last_heartbeat_at' => now()->subSeconds(120),
            'created_at' => now()->subSeconds(120),
        ]);
        $room->update(['creator_id' => $stalePlayer->id]);

        // Inactive finished room (should be deleted)
        $oldRoom = Room::create([
            'code' => 'OLD001',
            'type' => 'public',
            'status' => 'finished',
            'max_players' => 8,
            'rounds_per_game' => 3,
            'last_activity_at' => now()->subMinutes(11),
        ]);

        $this->artisan('app:clean-inactive-rooms')
            ->assertSuccessful();

        // Stale player room should be deleted (all players gone)
        $this->assertNull(Room::find($room->id));

        // Inactive finished room should be deleted
        $this->assertNull(Room::find($oldRoom->id));
    }

    public function test_command_does_not_touch_active_players(): void
    {
        $room = Room::create([
            'code' => 'ACTIVE',
            'type' => 'public',
            'status' => 'waiting',
            'max_players' => 8,
            'rounds_per_game' => 3,
        ]);
        $player = Player::create([
            'nickname' => 'Active',
            'room_id' => $room->id,
        ]);
        $room->update(['creator_id' => $player->id]);

        $this->artisan('app:clean-inactive-rooms')
            ->assertSuccessful();

        $this->assertEquals(1, $room->fresh()->players()->count());
    }
}
```

- [ ] **Step 2: Run the test**

Run: `cd /home/mamoun/games/imposter/backend && php artisan test --filter=CleanInactiveRoomsCommandTest`
Expected: All tests pass.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/CleanInactiveRoomsCommandTest.php
git commit -m "test: CleanInactiveRooms command integration test"
```

---

### Task 12: Final verification

- [ ] **Step 1: Run the full test suite**

Run: `cd /home/mamoun/games/imposter/backend && php artisan test`
Expected: All tests pass.

- [ ] **Step 2: Run the artisan command manually to verify it works**

Run: `cd /home/mamoun/games/imposter/backend && php artisan app:clean-inactive-rooms`
Expected: Command exits with code 0, no errors.

- [ ] **Step 3: Verify no leftover references to old code**

Run: `cd /home/mamoun/games/imposter/backend && grep -rn 'cleanAllStale\|purgeStalePlayers' app/ --include='*.php'`
Expected: Only references should be in `GameService.php` (the thin wrapper methods) and `RoomCleanupService.php` (the actual implementation). No standalone cleanup logic remains in `GameService`.
