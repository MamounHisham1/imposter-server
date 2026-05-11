<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GameEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The data payload for the event.
     *
     * @var array
     */
    public array $data;

    /**
     * The room ID this event is scoped to.
     *
     * @var int
     */
    public int $roomId;

    /**
     * Create a new game event instance.
     *
     * @param  int  $roomId  The room ID for broadcasting channel.
     * @param  string  $type  The event type (e.g., 'player_joined', 'game_started').
     * @param  array  $payload  Additional event data.
     */
    public function __construct(int $roomId, string $type, array $payload = [])
    {
        $this->roomId = $roomId;
        $this->data = array_merge(['type' => $type], $payload);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('room.'.$this->roomId),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'game.event';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith(): array
    {
        return $this->data;
    }
}
