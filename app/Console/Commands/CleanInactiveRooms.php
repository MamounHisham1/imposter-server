<?php

namespace App\Console\Commands;

use App\Events\GameEvent;
use App\Events\RoomListEvent;
use App\Models\Player;
use App\Models\Room;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('app:clean-inactive-rooms')]
#[Description('Delete inactive rooms and remove disconnected players')]
class CleanInactiveRooms extends Command
{
    public function handle(): int
    {
        $this->removeStalePlayers();
        $this->removeInactiveRooms();

        return self::SUCCESS;
    }

    private function removeStalePlayers(): void
    {
        $staleThreshold = now()->subSeconds(60);

        $stalePlayers = Player::where(function ($q) use ($staleThreshold) {
            $q->where('last_heartbeat_at', '<', $staleThreshold)
                ->orWhereNull('last_heartbeat_at');
        })
            ->where('created_at', '<', $staleThreshold)
            ->get();

        foreach ($stalePlayers as $player) {
            $room = $player->room;
            if (! $room) {
                $player->delete();
                continue;
            }

            $wasCreator = $player->id === $room->creator_id;
            $playerId = $player->id;

            $player->delete();

            $remainingPlayers = $room->fresh()->players()->count();

            if ($remainingPlayers === 0) {
                $room->delete();

                if ($room->type === 'public') {
                    broadcast(new RoomListEvent('removed', ['id' => $room->id, 'code' => $room->code]));
                }

                broadcast(new GameEvent($room->id, 'room_deleted', [
                    'code' => $room->code,
                ]));

                $this->info("Room {$room->code} deleted (all players disconnected)");

                continue;
            }

            if ($wasCreator) {
                $newCreator = $room->fresh()->players()->orderBy('id')->first();
                $room->update(['creator_id' => $newCreator->id]);

                broadcast(new GameEvent($room->id, 'creator_changed', [
                    'new_creator_id' => $newCreator->id,
                ]));
            }

            broadcast(new GameEvent($room->id, 'player_left', [
                'player_id' => $playerId,
            ]));

            if ($room->type === 'public' && $room->status === 'waiting') {
                broadcast(new RoomListEvent('updated', [
                    'id' => $room->id,
                    'code' => $room->code,
                    'players_count' => $remainingPlayers,
                    'max_players' => $room->max_players,
                ]));
            }

            $this->info("Player {$playerId} removed from room {$room->code} (heartbeat stale)");
        }
    }

    private function removeInactiveRooms(): void
    {
        $thresholds = [
            'waiting' => now()->subMinutes(30),
            'finished' => now()->subMinutes(10),
            'playing' => now()->subMinutes(60),
            'voting' => now()->subMinutes(60),
        ];

        foreach ($thresholds as $status => $threshold) {
            $deleted = Room::where('status', $status)
                ->where(function ($q) use ($threshold) {
                    $q->where('last_activity_at', '<', $threshold)
                        ->orWhereNull('last_activity_at');
                })
                ->delete();

            if ($deleted > 0) {
                $this->info("Deleted {$deleted} {$status} room(s)");
            }
        }
    }
}
