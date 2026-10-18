<?php

namespace App\Services;

use App\Jobs\DeliverRoomEvent;
use App\Models\RoomEvent;
use Illuminate\Support\Facades\DB;

class RoomOutboxDispatcher
{
    public function dispatch(string $eventId): void
    {
        DB::transaction(function () use ($eventId) {
            $event = RoomEvent::query()->whereKey($eventId)->lockForUpdate()->first();
            if (! $event || $event->published_at || ($event->queued_at && $event->queued_at->gt(now()->subMinute()))) {
                return;
            }

            $event->forceFill([
                'queued_at' => now(),
                'attempts' => $event->attempts + 1,
            ])->save();

            DeliverRoomEvent::dispatch($event->id)->afterCommit();
        });
    }

    public function dispatchPending(int $limit = 100): int
    {
        $pending = RoomEvent::query()
            ->whereNull('published_at')
            ->where(function ($query) {
                $query->whereNull('queued_at')->orWhere('queued_at', '<=', now()->subMinute());
            })
            ->orderBy('occurred_at')
            ->limit($limit)
            ->pluck('id');

        foreach ($pending as $eventId) {
            $this->dispatch($eventId);
        }

        return $pending->count();
    }
}
