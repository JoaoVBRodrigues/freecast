<?php

namespace App\Services;

use App\Models\Room;
use App\Models\RoomCommandReceipt;
use App\Models\RoomEvent;
use App\Models\RoomEventSequence;
use App\Models\RoomMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class RoomSyncService
{
    /**
     * Execute a durable command once and persist its response with its events.
     *
     * @param  array<string, mixed>  $input
     * @param  callable(string): array<string, mixed>  $handler
     * @return array<string, mixed>
     */
    public function execute(
        Room $room,
        User $user,
        string $operation,
        string $idempotencyKey,
        array $input,
        callable $handler,
    ): array {
        return DB::transaction(function () use ($room, $user, $operation, $idempotencyKey, $input, $handler) {
            Room::query()->whereKey($room->id)->lockForUpdate()->firstOrFail();
            abort_unless(RoomMembership::query()
                ->where('room_id', $room->id)
                ->where('user_id', $user->id)
                ->exists(), 403);

            $requestHash = hash('sha256', json_encode($this->canonicalize($input), JSON_THROW_ON_ERROR));
            $receipt = RoomCommandReceipt::query()
                ->where('room_id', $room->id)
                ->where('user_id', $user->id)
                ->where('operation', $operation)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($receipt) {
                if (! hash_equals($receipt->request_hash, $requestHash)) {
                    throw new ConflictHttpException('Esta chave de idempotência já foi usada com outro conteúdo.');
                }

                return [...$receipt->response, 'replayed' => true];
            }

            $commandId = (string) Str::uuid();
            $result = $handler($commandId);
            $response = [...$result, 'commandId' => $commandId];

            RoomCommandReceipt::query()->create([
                'room_id' => $room->id,
                'user_id' => $user->id,
                'operation' => $operation,
                'idempotency_key' => $idempotencyKey,
                'request_hash' => $requestHash,
                'command_id' => $commandId,
                'response' => $response,
            ]);

            return [...$response, 'replayed' => false];
        }, 3);
    }

    /**
     * Record an audience-scoped event inside the current domain transaction.
     *
     * @param  array<string, mixed>  $payload
     */
    public function record(
        Room $room,
        string $audience,
        string $type,
        ?string $entityId,
        ?int $entityRevision,
        string $commandId,
        string $idempotencyKey,
        array $payload,
    ): RoomEvent {
        $sequence = RoomEventSequence::query()->firstOrCreate(
            ['room_id' => $room->id, 'audience' => $audience],
            ['sequence' => 0],
        );
        $sequence->sequence++;
        $sequence->save();

        $event = RoomEvent::query()->create([
            'id' => $eventId = (string) Str::uuid(),
            'room_id' => $room->id,
            'audience' => $audience,
            'sequence' => $sequence->sequence,
            'event_type' => $type,
            'entity_id' => $entityId,
            'entity_revision' => $entityRevision,
            'command_id' => $commandId,
            'payload' => [
                'schemaVersion' => 1,
                'eventId' => $eventId,
                'roomId' => $room->id,
                'stream' => str_starts_with($audience, 'user:') ? 'user' : $audience,
                'sequence' => $sequence->sequence,
                'type' => $type,
                'entityId' => $entityId,
                'entityRevision' => $entityRevision,
                'commandId' => $commandId,
                'idempotencyKey' => $idempotencyKey,
                'occurredAt' => now()->toIso8601String(),
                'payload' => $payload,
            ],
            'occurred_at' => now(),
        ]);

        DB::afterCommit(fn () => app(RoomOutboxDispatcher::class)->dispatch($event->id));

        return $event;
    }

    /**
     * @param  mixed  $value
     * @return mixed
     */
    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        foreach ($value as $key => $child) {
            $value[$key] = $this->canonicalize($child);
        }

        return $value;
    }
}
