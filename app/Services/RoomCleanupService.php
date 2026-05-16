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
            return new CleanupResult;
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

    private function removePlayersFromRoom(Room $room, array $playerIds, bool $broadcastGameEvents): CleanupResult
    {
        if (empty($playerIds)) {
            return new CleanupResult;
        }

        // For mid-game rooms, delegate to GameService::leaveRoom for each stale player
        // so that proper imposter-fled / game-abort / hint-adjustment logic runs.
        $isMidGame = in_array($room->status, ['playing', 'voting', 'round_result']);

        if ($isMidGame && $broadcastGameEvents) {
            $gameService = app(GameService::class);
            foreach ($playerIds as $pid) {
                try {
                    $gameService->leaveRoom($pid);
                } catch (\Exception $e) {
                    // Player may have already been removed; skip silently
                }
            }

            $room = $room->fresh();
            if (! $room) {
                return new CleanupResult(
                    roomDeleted: true,
                    removedPlayerCount: count($playerIds),
                );
            }

            $remainingCount = $room->players()->count();

            return new CleanupResult(
                removedPlayerCount: count($playerIds),
            );
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
