<?php

namespace App\Services;

use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Illuminate\Support\Facades\Broadcast;
use LogicException;

class ReverbConnectionTerminator
{
    public function terminate(int $userId): void
    {
        if (config('broadcasting.default') !== 'reverb') {
            throw new LogicException('A revogação imediata exige que Reverb seja o broadcaster ativo.');
        }

        $broadcaster = Broadcast::connection('reverb');
        if (! $broadcaster instanceof PusherBroadcaster) {
            throw new LogicException('O broadcaster Reverb não expõe o cliente Pusher esperado.');
        }

        $broadcaster->getPusher()->terminateUserConnections((string) $userId);
    }
}
