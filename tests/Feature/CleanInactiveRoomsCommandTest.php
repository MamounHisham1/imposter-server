<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CleanInactiveRoomsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_deletes_stale_players_and_inactive_rooms(): void
    {
        Event::fake();

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
        ]);
        $room->update(['creator_id' => $stalePlayer->id]);

        DB::table('players')->where('id', $stalePlayer->id)->update([
            'last_heartbeat_at' => now()->subSeconds(120),
            'created_at' => now()->subSeconds(120),
        ]);

        $oldRoom = Room::create([
            'code' => 'OLD001',
            'type' => 'public',
            'status' => 'finished',
            'max_players' => 8,
            'rounds_per_game' => 3,
        ]);

        DB::table('rooms')->where('id', $oldRoom->id)->update([
            'last_activity_at' => now()->subMinutes(11),
        ]);

        $this->artisan('app:clean-inactive-rooms')
            ->assertSuccessful();

        $this->assertNull(Room::find($room->id));
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
