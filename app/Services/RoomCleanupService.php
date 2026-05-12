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
