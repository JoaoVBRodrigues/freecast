<?php

namespace App\Console\Commands;

use App\Services\RoomOutboxDispatcher;
use Illuminate\Console\Command;

class DispatchRoomOutbox extends Command
{
    protected $signature = 'rooms:dispatch-outbox {--limit=100}';

    protected $description = 'Requeue committed room events that have not been delivered';

    public function handle(RoomOutboxDispatcher $dispatcher): int
    {
        $limit = (int) $this->option('limit');
        if ($limit < 1 || $limit > 1000) {
            $this->error('The limit must be between 1 and 1000.');

            return self::INVALID;
        }

        $count = $dispatcher->dispatchPending($limit);
        $this->info("Queued {$count} pending room event(s).");

        return self::SUCCESS;
    }
}
