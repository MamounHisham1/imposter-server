<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoomListEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $action,
        public array $room = [],
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('public-rooms'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'rooms.event';
    }

    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'room' => $this->room,
        ];
    }
}
