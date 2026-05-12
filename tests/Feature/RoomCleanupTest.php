<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Models\Room;
use App\Services\RoomCleanupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    private static int $roomCounter = 0;

    private function uniqueCode(): string
    {
        self::$roomCounter++;
        return 'CODE' . str_pad((string) self::$roomCounter, 3, '0', STR_PAD_LEFT);
    }

    private function createRoomWithPlayers(string $status = 'waiting', int $count = 3, string $type = 'public'): array
    {
        $room = Room::create([
            'code' => $this->uniqueCode(),
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

    private function makePlayerStale(Player $player): void
    {
        DB::table('players')
            ->where('id', $player->id)
            ->update([
                'last_heartbeat_at' => now()->subSeconds(120),
                'created_at' => now()->subSeconds(120),
            ]);
    }

    private function createInactiveRoom(string $status, string $code, string $type = 'public'): Room
    {
        $room = Room::create([
            'code' => $code,
            'type' => $type,
            'status' => $status,
            'max_players' => 8,
            'rounds_per_game' => 3,
        ]);

        DB::table('rooms')
            ->where('id', $room->id)
            ->update(['last_activity_at' => now()->subMinutes(61)]);

        return $room->fresh();
    }

    // --- purgeStalePlayers tests ---

    public function test_no_stale_players_returns_empty_result(): void
    {
        ['room' => $room] = $this->createRoomWithPlayers();

        $result = $this->service->purgeStalePlayers($room, broadcastGameEvents: true);

        $this->assertFalse($result->roomDeleted);
        $this->assertEquals(0, $result->removedPlayerCount);
        $this->assertEquals(3, $room->fresh()->players()->count());
        $this->assertFalse($result->creatorChanged);
    }

    public function test_stale_player_without_broadcast_deletes_player(): void
    {
        ['room' => $room, 'players' => $players] = $this->createRoomWithPlayers(count: 4);

        $this->makePlayerStale($players[0]);

        Event::fake();

        $result = $this->service->purgeStalePlayers($room, broadcastGameEvents: false);

        $this->assertEquals(1, $result->removedPlayerCount);
        $this->assertFalse($result->roomDeleted);
        $this->assertEquals(3, $room->fresh()->players()->count());

        Event::assertNotDispatched(\App\Events\GameEvent::class);
    }

    public function test_stale_player_with_broadcast_dispatches_events(): void
    {
        ['room' => $room, 'players' => $players] = $this->createRoomWithPlayers(count: 4);

        $this->makePlayerStale($players[0]);

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
            $this->makePlayerStale($p);
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

        $this->makePlayerStale($players[0]);

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

        $this->makePlayerStale($players[0]);
        $this->makePlayerStale($players[1]);

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
            $this->makePlayerStale($p);
        }

        Event::fake();

        $result = $this->service->purgeStalePlayers($room, broadcastGameEvents: true);

        $this->assertTrue($result->roomDeleted);
        Event::assertNotDispatched(\App\Events\RoomListEvent::class);
    }

    public function test_new_player_grace_period_not_purged(): void
    {
        ['room' => $room, 'players' => $players] = $this->createRoomWithPlayers(count: 2);

        // Null heartbeat but recently created — should not be purged
        DB::table('players')
            ->where('id', $players[0]->id)
            ->update(['last_heartbeat_at' => null]);

        $result = $this->service->purgeStalePlayers($room, broadcastGameEvents: true);

        $this->assertEquals(0, $result->removedPlayerCount);
        $this->assertEquals(2, $room->fresh()->players()->count());
    }

    // --- purgeStalePlayersFromAllRooms tests ---

    public function test_purge_all_stale_across_multiple_rooms(): void
    {
        $room1 = $this->createRoomWithPlayers(count: 2);
        $room2 = $this->createRoomWithPlayers(count: 2);

        $this->makePlayerStale($room1['players'][0]);
        $this->makePlayerStale($room2['players'][0]);

        Event::fake();

        $this->service->purgeStalePlayersFromAllRooms(broadcastGameEvents: true);

        $this->assertEquals(1, $room1['room']->fresh()->players()->count());
        $this->assertEquals(1, $room2['room']->fresh()->players()->count());
    }

    public function test_purge_all_deletes_empty_rooms(): void
    {
        $data = $this->createRoomWithPlayers(count: 1);

        $this->makePlayerStale($data['players'][0]);

        Event::fake();

        $this->service->purgeStalePlayersFromAllRooms(broadcastGameEvents: true);

        $this->assertNull(Room::find($data['room']->id));
    }

    // --- removeInactiveRooms tests ---

    public function test_waiting_room_deleted_after_30_minutes(): void
    {
        $room = $this->createInactiveRoom('waiting', 'OLD001');

        DB::table('rooms')->where('id', $room->id)->update([
            'last_activity_at' => now()->subMinutes(31),
        ]);
        $room = $room->fresh();

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
        ]);

        $deleted = $this->service->removeInactiveRooms();

        $this->assertEquals(0, $deleted);
        $this->assertNotNull(Room::find($room->id));
    }

    public function test_finished_room_deleted_after_10_minutes(): void
    {
        $room = $this->createInactiveRoom('finished', 'DONE01');

        DB::table('rooms')->where('id', $room->id)->update([
            'last_activity_at' => now()->subMinutes(11),
        ]);

        $deleted = $this->service->removeInactiveRooms();

        $this->assertEquals(1, $deleted);
        $this->assertNull(Room::find($room->id));
    }

    public function test_playing_room_deleted_after_60_minutes(): void
    {
        $room = $this->createInactiveRoom('playing', 'PLAY01');

        $deleted = $this->service->removeInactiveRooms();

        $this->assertEquals(1, $deleted);
        $this->assertNull(Room::find($room->id));
    }

    public function test_round_result_room_deleted_after_60_minutes(): void
    {
        $room = $this->createInactiveRoom('round_result', 'RSLT01');

        $deleted = $this->service->removeInactiveRooms();

        $this->assertEquals(1, $deleted);
        $this->assertNull(Room::find($room->id));
    }

    public function test_voting_room_deleted_after_60_minutes(): void
    {
        $room = $this->createInactiveRoom('voting', 'VOTE01');

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
        ]);

        DB::table('rooms')->where('id', $room->id)->update(['last_activity_at' => null]);

        $deleted = $this->service->removeInactiveRooms();

        $this->assertEquals(1, $deleted);
        $this->assertNull(Room::find($room->id));
    }

    public function test_inactive_private_room_deleted_without_roomlist_event(): void
    {
        $room = $this->createInactiveRoom('finished', 'PRIV01', 'private');

        Event::fake();

        $deleted = $this->service->removeInactiveRooms();

        $this->assertEquals(1, $deleted);
        $this->assertNull(Room::find($room->id));
        Event::assertNotDispatched(\App\Events\RoomListEvent::class);
        Event::assertDispatched(\App\Events\GameEvent::class);
    }
}
