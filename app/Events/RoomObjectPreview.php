<?php

namespace App\Events;

use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoomObjectPreview implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, int|float|string>  $payload
     */
    public function __construct(public int $roomId, public string $audience, public array $payload)
    {
    }

    public function broadcastOn(): array
    {
        if ($this->audience === 'room') {
            return [new PresenceChannel('rooms.'.$this->roomId)];
        }

        return [new PrivateChannel('rooms.'.$this->roomId.'.masters')];
    }

    public function broadcastAs(): string
    {
        return 'room.object.preview';
    }

    /**
     * @return array<string, int|float|string>
     */
    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
