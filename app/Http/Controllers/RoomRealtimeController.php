<?php

namespace App\Http\Controllers;

use App\Events\RoomObjectPreview;
use App\Models\DiceRoll;
use App\Models\Room;
use App\Models\RoomAsset;
use App\Models\RoomMacro;
use App\Models\RoomMap;
use App\Models\RoomMapObject;
use App\Models\RoomMessage;
use App\Models\RoomMembership;
use App\Models\User;
use App\Services\DiceExpression;
use App\Services\RoomSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class RoomRealtimeController extends Controller
{
    public function events(Request $request, Room $room): JsonResponse
    {
        Gate::authorize('view', $room);
        $validated = $request->validate([
            'after.room' => ['sometimes', 'integer', 'min:0'],
            'after.masters' => ['sometimes', 'integer', 'min:0'],
            'after.user' => ['sometimes', 'integer', 'min:0'],
        ]);

        $audiences = ['room' => 'room'];
        if (Gate::allows('manage', $room)) {
            $audiences['masters'] = 'masters';
        }
        $audiences['user'] = 'user:'.$request->user()->id;

        $result = [];
        $next = [];
        $hasMore = false;
        foreach ($audiences as $cursorKey => $audience) {
            $cursor = (int) data_get($validated, 'after.'.$cursorKey, 0);
            $query = $room->events()
                ->where('audience', $audience)
                ->where('sequence', '>', $cursor)
                ->orderBy('sequence');
            $batch = $query->limit(201)->get();
            $hasMore = $hasMore || $batch->count() > 200;
            $visible = $batch->take(200);
            foreach ($visible as $event) {
                $result[] = $event->payload;
            }
            $next[$cursorKey] = $visible->last()?->sequence ?? $cursor;
        }

        usort($result, fn (array $left, array $right) => [$left['occurredAt'], $left['eventId']] <=> [$right['occurredAt'], $right['eventId']]);

        return response()->json([
            'events' => $result,
            'cursor' => $next,
            'hasMore' => $hasMore,
        ]);
    }

    public function history(Request $request, Room $room): JsonResponse
    {
        Gate::authorize('view', $room);
        $data = $request->validate([
            'before' => ['sometimes', 'integer', 'min:1'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $userId = $request->user()->id;
        $messages = $room->messages()
            ->with(['user:id,name', 'recipient:id,name'])
            ->where(function ($query) use ($userId) {
                $query->where('message_type', '!=', 'private')
                    ->orWhere('user_id', $userId)
                    ->orWhere('recipient_user_id', $userId);
            })
            ->when(isset($data['before']), fn ($query) => $query->where('id', '<', $data['before']))
            ->latest('id')
            ->limit($data['limit'] ?? 50)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (RoomMessage $message) => $this->messageData($message, $room, $request->user()));

        return response()->json([
            'messages' => $messages,
            'nextBefore' => $messages->first()['id'] ?? null,
        ]);
    }

    public function storeMessage(Request $request, Room $room, RoomSyncService $sync, DiceExpression $dice): JsonResponse
    {
        Gate::authorize('view', $room);
        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'message_type' => ['sometimes', Rule::in(['public', 'narration', 'private'])],
            'recipient_user_id' => ['required_if:message_type,private', 'nullable', 'integer', 'exists:users,id'],
            'asset_ids' => ['sometimes', 'array', 'max:4'],
            'asset_ids.*' => ['integer', 'distinct', 'exists:room_assets,id'],
            'idempotency_key' => ['required', 'string', 'min:16', 'max:128'],
        ]);

        $body = trim($data['body']);
        if ($body === '') {
            throw ValidationException::withMessages(['body' => 'A mensagem não pode estar vazia.']);
        }

        $type = $data['message_type'] ?? 'public';
        $recipientId = $data['recipient_user_id'] ?? null;
        $assets = $this->authorizedAssets($room, $data['asset_ids'] ?? [], $request->user());
        if ($type === 'private') {
            abort_unless($recipientId && $recipientId !== $request->user()->id, 422);
            abort_unless($room->memberships()->where('user_id', $recipientId)->exists(), 422);
            if (! Gate::allows('manage', $room)) {
                foreach ($assets as $asset) {
                    abort_if($asset->is_master_only || $asset->folder?->is_master_only, 403);
                }
            }
        } elseif ($recipientId !== null) {
            abort(422);
        }
        foreach ($assets as $asset) {
            $masterOnlyAsset = $asset->is_master_only || $asset->folder?->is_master_only;
            if (! $masterOnlyAsset) {
                continue;
            }
            $recipientIsMaster = $type === 'private' && $room->memberships()
                ->where('user_id', $recipientId)
                ->whereIn('role', [RoomMembership::ROLE_OWNER, RoomMembership::ROLE_MASTER])
                ->exists();
            abort_unless($type === 'private' && Gate::allows('manage', $room) && $recipientIsMaster, 403);
        }

        $input = [
            'body' => $body,
            'message_type' => $type,
            'recipient_user_id' => $recipientId,
            'asset_ids' => $assets->modelKeys(),
        ];

        if ($type === 'public' && str_starts_with($body, '/r')) {
            if (! preg_match('/^\/r\s+(.+)$/i', $body, $match)) {
                throw ValidationException::withMessages(['body' => 'Use /r seguido de uma expressão de dados válida.']);
            }

            return response()->json($sync->execute(
                $room,
                $request->user(),
                'dice-roll',
                $data['idempotency_key'],
                ['expression' => $match[1]],
                function (string $commandId) use ($room, $request, $match, $dice, $sync, $data) {
                    $roll = $dice->roll($match[1]);
                    $record = DiceRoll::query()->create([
                        'room_id' => $room->id,
                        'user_id' => $request->user()->id,
                        'command_id' => $commandId,
                        'roll_index' => 0,
                        ...$roll,
                    ]);
                    $event = $sync->record($room, 'room', 'dice.rolled', (string) $record->id, 1, $commandId, $data['idempotency_key'], [
                        'id' => $record->id,
                        'author' => $request->user()->name,
                        ...$roll,
                        'created_at' => $record->created_at->toIso8601String(),
                    ]);

                    return ['rollId' => $record->id, 'sequence' => $event->sequence, 'result' => $roll];
                },
            ));
        }

        if ($type === 'public' && ! str_starts_with($body, '/')) {
            $macro = $this->resolveMacro($room, $request->user(), $body);
            if ($macro) {
                return $this->executeMacroRequest($request, $room, $macro, $data['idempotency_key'], $sync, $dice);
            }
        }

        $response = $sync->execute(
            $room,
            $request->user(),
            'message-create',
            $data['idempotency_key'],
            $input,
            function (string $commandId) use ($room, $request, $body, $type, $recipientId, $assets, $sync, $data) {
                $message = $room->messages()->create([
                    'user_id' => $request->user()->id,
                    'body' => $body,
                    'message_type' => $type,
                    'recipient_user_id' => $recipientId,
                    'asset_ids' => $assets->modelKeys(),
                ])->load(['user:id,name', 'recipient:id,name']);
                $audiences = $type === 'private'
                    ? ['user:'.$request->user()->id, 'user:'.$recipientId]
                    : ['room'];
                $lastEvent = null;

                foreach ($audiences as $audience) {
                    $lastEvent = $sync->record(
                        $room,
                        $audience,
                        'message.created',
                        (string) $message->id,
                        1,
                        $commandId,
                        $data['idempotency_key'],
                        $this->messageData($message, $room, $request->user()),
                    );
                }

                return ['messageId' => $message->id, 'sequence' => $lastEvent?->sequence];
            },
        );

        return response()->json($response);
    }

    public function storeMacro(Request $request, Room $room, RoomSyncService $sync): JsonResponse
    {
        Gate::authorize('view', $room);
        $data = $request->validate([
            'scope' => ['required', Rule::in([RoomMacro::PERSONAL_ROOM, RoomMacro::PERSONAL_GLOBAL, RoomMacro::SHARED_ROOM])],
            'name' => ['required', 'string', 'max:40'],
            'body' => ['required', 'string', 'max:3000'],
            'idempotency_key' => ['required', 'string', 'min:16', 'max:128'],
        ]);
        $this->authorizeMacroScope($room, $request->user(), $data['scope']);
        $name = $this->cleanMacroName($data['name']);
        $scopeKey = $data['scope'] === RoomMacro::SHARED_ROOM ? 'room:'.$room->id : 'user:'.$request->user()->id;
        $macroRoomId = $data['scope'] === RoomMacro::PERSONAL_GLOBAL ? null : $room->id;
        $macroUserId = $data['scope'] === RoomMacro::SHARED_ROOM ? null : $request->user()->id;

        $response = $sync->execute(
            $room,
            $request->user(),
            'macro-create',
            $data['idempotency_key'],
            ['scope' => $data['scope'], 'name' => $name, 'body' => $data['body']],
            function (string $commandId) use ($room, $request, $sync, $data, $name, $scopeKey, $macroRoomId, $macroUserId) {
                $macro = RoomMacro::query()->create([
                    'room_id' => $macroRoomId,
                    'user_id' => $macroUserId,
                    'scope' => $data['scope'],
                    'scope_key' => $scopeKey,
                    'name' => $name,
                    'name_key' => $this->macroNameKey($name),
                    'body' => $data['body'],
                ]);
                $audience = $data['scope'] === RoomMacro::SHARED_ROOM ? 'room' : 'user:'.$request->user()->id;
                $event = $sync->record($room, $audience, 'macro.updated', (string) $macro->id, $macro->revision, $commandId, $data['idempotency_key'], [
                    'id' => $macro->id,
                    'scope' => $macro->scope,
                    'name' => $macro->name,
                    'body' => $macro->body,
                    'revision' => $macro->revision,
                ]);

                return ['macroId' => $macro->id, 'revision' => $macro->revision, 'sequence' => $event->sequence];
            },
        );

        return response()->json($response, 201);
    }

    public function updateMacro(Request $request, Room $room, RoomMacro $macro, RoomSyncService $sync): JsonResponse
    {
        Gate::authorize('view', $room);
        abort_unless($macro->room_id === $room->id || ($macro->scope === RoomMacro::PERSONAL_GLOBAL && $macro->user_id === $request->user()->id), 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:40'],
            'body' => ['required', 'string', 'max:3000'],
            'expected_revision' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'string', 'min:16', 'max:128'],
        ]);
        $this->authorizeMacroScope($room, $request->user(), $macro->scope);
        $name = $this->cleanMacroName($data['name']);

        return response()->json($sync->execute(
            $room,
            $request->user(),
            'macro-update',
            $data['idempotency_key'],
            ['macro_id' => $macro->id, 'name' => $name, 'body' => $data['body'], 'expected_revision' => $data['expected_revision']],
            function (string $commandId) use ($room, $request, $macro, $sync, $data, $name) {
                $locked = RoomMacro::query()->whereKey($macro->id)->lockForUpdate()->firstOrFail();
                if ($locked->revision !== (int) $data['expected_revision']) {
                    throw new ConflictHttpException('A macro foi alterada. Atualize e tente novamente.');
                }
                $locked->update(['name' => $name, 'name_key' => $this->macroNameKey($name), 'body' => $data['body'], 'revision' => $locked->revision + 1]);
                $audience = $locked->scope === RoomMacro::SHARED_ROOM ? 'room' : 'user:'.$request->user()->id;
                $event = $sync->record($room, $audience, 'macro.updated', (string) $locked->id, $locked->revision, $commandId, $data['idempotency_key'], [
                    'id' => $locked->id, 'scope' => $locked->scope, 'name' => $locked->name, 'body' => $locked->body, 'revision' => $locked->revision,
                ]);

                return ['macroId' => $locked->id, 'revision' => $locked->revision, 'sequence' => $event->sequence];
            },
        ));
    }

    public function executeMacro(Request $request, Room $room, RoomMacro $macro, RoomSyncService $sync, DiceExpression $dice): JsonResponse
    {
        Gate::authorize('view', $room);
        abort_unless($macro->room_id === $room->id || ($macro->scope === RoomMacro::PERSONAL_GLOBAL && $macro->user_id === $request->user()->id), 404);
        abort_unless(
            $macro->scope === RoomMacro::SHARED_ROOM
                || $macro->user_id === $request->user()->id,
            404,
        );
        $data = $request->validate(['idempotency_key' => ['required', 'string', 'min:16', 'max:128']]);

        return $this->executeMacroRequest($request, $room, $macro, $data['idempotency_key'], $sync, $dice);
    }

    public function deleteMacro(Request $request, Room $room, RoomMacro $macro, RoomSyncService $sync): JsonResponse
    {
        Gate::authorize('view', $room);
        abort_unless(
            $macro->room_id === $room->id
                || ($macro->scope === RoomMacro::PERSONAL_GLOBAL && $macro->user_id === $request->user()->id),
            404,
        );
        $this->authorizeMacroScope($room, $request->user(), $macro->scope);
        $data = $request->validate([
            'expected_revision' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'string', 'min:16', 'max:128'],
        ]);

        return response()->json($sync->execute(
            $room,
            $request->user(),
            'macro-delete',
            $data['idempotency_key'],
            ['macro_id' => $macro->id, 'expected_revision' => $data['expected_revision']],
            function (string $commandId) use ($room, $request, $macro, $sync, $data) {
                $locked = RoomMacro::query()->whereKey($macro->id)->lockForUpdate()->firstOrFail();
                if ($locked->revision !== (int) $data['expected_revision']) {
                    throw new ConflictHttpException('A macro foi alterada. Atualize e tente novamente.');
                }
                $scope = $locked->scope;
                $id = $locked->id;
                $locked->delete();
                $audience = $scope === RoomMacro::SHARED_ROOM ? 'room' : 'user:'.$request->user()->id;
                $event = $sync->record($room, $audience, 'macro.deleted', (string) $id, null, $commandId, $data['idempotency_key'], ['id' => $id]);

                return ['macroId' => $id, 'sequence' => $event->sequence];
            },
        ));
    }

    public function activateMap(Request $request, Room $room, RoomMap $map, RoomSyncService $sync): JsonResponse
    {
        Gate::authorize('manage', $room);
        abort_unless($map->room_id === $room->id, 404);
        $data = $request->validate(['idempotency_key' => ['required', 'string', 'min:16', 'max:128']]);

        return response()->json($sync->execute(
            $room,
            $request->user(),
            'map-activate',
            $data['idempotency_key'],
            ['map_id' => $map->id],
            function (string $commandId) use ($room, $map, $sync, $data) {
                $lockedRoom = Room::query()->whereKey($room->id)->lockForUpdate()->firstOrFail();
                $lockedRoom->update(['active_map_id' => $map->id, 'revision' => $lockedRoom->revision + 1]);
                $event = $sync->record($room, 'room', 'room.active_map', (string) $map->id, $lockedRoom->revision, $commandId, $data['idempotency_key'], [
                    'map_id' => $map->id,
                    'title' => $map->title,
                ]);

                return ['mapId' => $map->id, 'revision' => $lockedRoom->revision, 'sequence' => $event->sequence];
            },
        ));
    }

    public function previewObject(Request $request, Room $room, RoomMap $map, RoomMapObject $object): JsonResponse
    {
        Gate::authorize('view', $room);
        abort_unless($map->room_id === $room->id && $object->room_map_id === $map->id, 404);
        $data = $request->validate([
            'x' => ['required', 'numeric', 'between:-100000,100000'],
            'y' => ['required', 'numeric', 'between:-100000,100000'],
            'revision' => ['required', 'integer', 'min:1'],
        ]);

        $object->load(['layer', 'asset.folder']);
        $isManager = Gate::allows('manage', $room);
        abort_if($object->revision !== (int) $data['revision'], 409);
        if (! $isManager) {
            abort_unless(
                $object->assigned_user_id === $request->user()->id
                    && $object->visible
                    && ! $object->locked
                    && $object->layer->type === 'tokens'
                    && $object->layer->visible
                    && ! $object->layer->locked
                    && ! $object->layer->is_master_only
                    && ! $object->asset->is_master_only
                    && ! $object->asset->folder?->is_master_only,
                403,
            );
        }

        $audience = $object->layer->is_master_only || $object->asset->is_master_only || $object->asset->folder?->is_master_only
            ? 'masters'
            : 'room';
        event(new RoomObjectPreview($room->id, $audience, [
            'roomId' => $room->id,
            'mapId' => $map->id,
            'objectId' => $object->id,
            'x' => (float) $data['x'],
            'y' => (float) $data['y'],
            'revision' => $object->revision,
            'userId' => $request->user()->id,
        ]));

        return response()->json(['accepted' => true]);
    }

    private function executeMacroRequest(
        Request $request,
        Room $room,
        RoomMacro $macro,
        string $idempotencyKey,
        RoomSyncService $sync,
        DiceExpression $dice,
    ): JsonResponse {
        $this->authorizeMacroRead($room, $request->user(), $macro);
        $lines = preg_split('/\R/u', $macro->body) ?: [];
        $lines = array_values(array_filter(array_map('trim', $lines), fn (string $line) => $line !== ''));
        if (count($lines) > 20) {
            throw ValidationException::withMessages(['body' => 'Uma macro pode executar no máximo 20 ações.']);
        }
        foreach ($lines as $line) {
            if (strlen($line) > 2000 || (str_starts_with($line, '/') && ! preg_match('/^\/r\s+.+$/i', $line))) {
                throw ValidationException::withMessages(['body' => 'Macros aceitam apenas mensagens e comandos /r válidos.']);
            }
        }

        $response = $sync->execute(
            $room,
            $request->user(),
            'macro-execute',
            $idempotencyKey,
            ['macro_id' => $macro->id, 'revision' => $macro->revision, 'body' => $macro->body],
            function (string $commandId) use ($request, $room, $macro, $lines, $sync, $dice, $idempotencyKey) {
                $created = [];
                $rollIndex = 0;
                foreach ($lines as $line) {
                    if (preg_match('/^\/r\s+(.+)$/i', $line, $match)) {
                        $roll = $dice->roll($match[1]);
                        $record = DiceRoll::query()->create([
                            'room_id' => $room->id,
                            'user_id' => $request->user()->id,
                            'command_id' => $commandId,
                            'roll_index' => $rollIndex++,
                            ...$roll,
                        ]);
                        $event = $sync->record($room, 'room', 'dice.rolled', (string) $record->id, 1, $commandId, $idempotencyKey, [
                            'id' => $record->id,
                            'author' => $request->user()->name,
                            ...$roll,
                            'created_at' => $record->created_at->toIso8601String(),
                        ]);
                        $created[] = ['type' => 'roll', 'id' => $record->id, 'sequence' => $event->sequence];
                        continue;
                    }

                    $message = $room->messages()->create([
                        'user_id' => $request->user()->id,
                        'body' => $line,
                        'message_type' => 'public',
                    ])->load('user:id,name');
                    $event = $sync->record($room, 'room', 'message.created', (string) $message->id, 1, $commandId, $idempotencyKey, $this->messageData($message, $room, $request->user()));
                    $created[] = ['type' => 'message', 'id' => $message->id, 'sequence' => $event->sequence];
                }

                return ['macroId' => $macro->id, 'actions' => $created];
            },
        );

        return response()->json($response);
    }

    private function resolveMacro(Room $room, User $user, string $name): ?RoomMacro
    {
        $nameKey = $this->macroNameKey($name);
        if ($nameKey === '') {
            return null;
        }

        return RoomMacro::query()
            ->where('name_key', $nameKey)
            ->where(function ($query) use ($room, $user) {
                $query->where(fn ($personal) => $personal->where('scope', RoomMacro::PERSONAL_ROOM)->where('room_id', $room->id)->where('user_id', $user->id))
                    ->orWhere(fn ($global) => $global->where('scope', RoomMacro::PERSONAL_GLOBAL)->whereNull('room_id')->where('user_id', $user->id))
                    ->orWhere(fn ($shared) => $shared->where('scope', RoomMacro::SHARED_ROOM)->where('room_id', $room->id)->whereNull('user_id'));
            })
            ->orderByRaw("CASE scope WHEN 'personal_room' THEN 0 WHEN 'personal_global' THEN 1 ELSE 2 END")
            ->first();
    }

    private function authorizeMacroScope(Room $room, User $user, string $scope): void
    {
        if ($scope === RoomMacro::SHARED_ROOM) {
            Gate::authorize('manage', $room);
        } else {
            Gate::authorize('view', $room);
        }
    }

    private function cleanMacroName(string $name): string
    {
        $name = preg_replace('/[ \/.]+/u', '', trim($name)) ?? '';
        if ($name === '' || $this->macroNameKey($name) === '') {
            throw ValidationException::withMessages(['name' => 'Informe um nome de macro válido.']);
        }

        return $name;
    }

    private function macroNameKey(string $name): string
    {
        $normalized = preg_replace('/[ \/.]+/u', '', $name) ?? '';

        return Str::upper(Str::ascii($normalized));
    }

    private function authorizedAssets(Room $room, array $ids, User $user)
    {
        $canManage = Gate::allows('manage', $room);
        $assets = RoomAsset::query()->with('folder')->whereIn('id', $ids)->get();
        abort_unless($assets->count() === count(array_unique($ids)), 422);
        foreach ($assets as $asset) {
            abort_unless($asset->room_id === $room->id, 422);
            abort_if(! $canManage && ($asset->is_master_only || $asset->folder?->is_master_only), 403);
        }

        return $assets;
    }

    private function authorizeMacroRead(Room $room, User $user, RoomMacro $macro): void
    {
        if ($macro->scope === RoomMacro::SHARED_ROOM) {
            abort_unless($macro->room_id === $room->id, 404);

            return;
        }

        abort_unless($macro->user_id === $user->id, 404);
        if ($macro->scope === RoomMacro::PERSONAL_ROOM) {
            abort_unless($macro->room_id === $room->id, 404);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function messageData(RoomMessage $message, Room $room, User $viewer): array
    {
        $assets = $this->authorizedAssets($room, $message->asset_ids ?? [], $viewer)
            ->map(fn (RoomAsset $asset) => [
                'id' => $asset->id,
                'name' => $asset->name,
                'url' => route('maps.assets.show', [$room, $asset]),
            ])
            ->values();

        return [
            'id' => $message->id,
            'author_id' => $message->user_id,
            'author' => $message->user->name,
            'recipient_id' => $message->message_type === 'private' ? $message->recipient_user_id : null,
            'recipient' => $message->message_type === 'private' ? $message->recipient?->name : null,
            'message_type' => $message->message_type,
            'body' => $message->body,
            'assets' => $assets,
            'created_at' => $message->created_at->toIso8601String(),
        ];
    }
}
