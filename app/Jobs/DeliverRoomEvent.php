<?php

namespace App\Jobs;

use App\Events\RoomStateChanged;
use App\Models\RoomEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeliverRoomEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 20;

    public function __construct(public string $eventId)
    {
    }

    public function backoff(): array
    {
        return [2, 5, 15, 30];
    }

    public function handle(): void
    {
        $event = RoomEvent::query()->find($this->eventId);
        if (! $event || $event->published_at) {
            return;
        }

        event(new RoomStateChanged($event->audience, $event->payload));

        $event->forceFill(['published_at' => now()])->save();
    }
}
