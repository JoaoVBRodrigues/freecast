<?php

namespace App\Events;

use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoomStateChanged implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public string $audience, public array $payload)
    {
    }

    public function broadcastOn(): array
    {
        [$scope, $id] = array_pad(explode(':', $this->audience, 2), 2, null);
        $roomId = (int) $this->payload['roomId'];

        return match ($scope) {
            'room' => [new PresenceChannel('rooms.'.$roomId)],
            'masters' => [new PrivateChannel('rooms.'.$roomId.'.masters')],
            'user' => [new PrivateChannel('users.'.$id)],
            default => [],
        };
    }

    public function broadcastAs(): string
    {
        return 'room.state.changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $payload = $this->payload;
        if (str_starts_with($payload['type'], 'map.object.')) {
            $payload['type'] = 'map.contents.changed';
            $payload['entityId'] = null; $payload['entityRevision'] = null;
            $payload['payload'] = ['map_id' => $payload['payload']['map_id'] ?? null];
        }
        return $payload;
    }
}
