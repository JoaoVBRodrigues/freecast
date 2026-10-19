<?php

namespace App\Http\Controllers;

use App\Models\{Room, RoomAsset, RoomCharacter, RoomCombat, RoomMapObject, RoomMembership};
use App\Services\{RoomCharacterData, RoomSyncService};
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{Gate, Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CharacterController extends Controller
{
    public function index(Request $request, Room $room, RoomCharacterData $data): JsonResponse
    {
        Gate::authorize('view', $room);

        return response()->json($data->snapshot($room, $request->user()))->header('Cache-Control', 'private, no-store');
    }

    public function portrait(Request $request, Room $room, RoomCharacter $character, RoomCharacterData $data)
    {
        Gate::authorize('view', $room);
        abort_unless($character->room_id === $room->id && $data->visible($room, $request->user(), $character), 404);
        $asset = $character->portrait;
        abort_unless($asset && $asset->room_id === $room->id, 404);
        abort_unless($data->canEdit($room, $request->user(), $character) || (! $asset->is_master_only && ! $asset->folder?->is_master_only), 403);

        return Storage::disk('local')->response($asset->path, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function command(Request $request, Room $room, RoomSyncService $sync, RoomCharacterData $projection): JsonResponse
    {
        Gate::authorize('view', $room);
        $input = $request->validate([
            'action' => ['required', Rule::in(['create', 'update', 'delete', 'link'])],
            'character_id' => ['required_unless:action,create', 'integer'],
            'expected_revision' => ['required_unless:action,create', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'string', 'min:16', 'max:128'],
            'name' => ['required_if:action,create', 'string', 'max:100'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'attributes' => ['sometimes', 'array', 'max:40'],
            'attributes.*' => ['array:name,value'],
            'attributes.*.name' => ['required', 'string', 'max:60'],
            'attributes.*.value' => ['required', 'string', 'max:200'],
            'resources' => ['sometimes', 'array', 'max:20'],
            'resources.*' => ['array:name,current,max'],
            'resources.*.name' => ['required', 'string', 'max:60'],
            'resources.*.current' => ['required', 'integer', 'between:0,1000000'],
            'resources.*.max' => ['required', 'integer', 'between:0,1000000'],
            'hp_current' => ['sometimes', 'integer', 'between:0,1000000'],
            'hp_max' => ['sometimes', 'integer', 'between:0,1000000'],
            'assigned_user_id' => ['sometimes', 'nullable', 'integer'],
            'portrait_asset_id' => ['sometimes', 'nullable', 'integer'],
            'is_public' => ['sometimes', 'boolean'],
            'token_id' => ['required_if:action,link', 'integer'],
            'token_revision' => ['required_if:action,link', 'integer', 'min:1'],
            'unlink' => ['sometimes', 'boolean'],
        ]);
        $key = $input['idempotency_key'];

        return response()->json($sync->execute($room, $request->user(), 'character-command', $key, $input,
            function (string $commandId) use ($request, $room, $sync, $projection, $input, $key) {
                $manager = Gate::allows('manage', $room);
                $action = $input['action'];
                if ($action === 'create') {
                    Gate::authorize('manage', $room);
                    $character = new RoomCharacter(['room_id' => $room->id]);
                } else {
                    $character = RoomCharacter::query()->where('room_id', $room->id)->whereKey($input['character_id'])->lockForUpdate()->firstOrFail();
                    abort_unless($projection->canEdit($room, $request->user(), $character), 403);
                    if ($character->revision !== $input['expected_revision']) {
                        throw new ConflictHttpException('A ficha foi alterada. Reabra antes de salvar.');
                    }
                }
                if (in_array($action, ['delete', 'link'])) {
                    Gate::authorize('manage', $room);
                }
                $fields = array_intersect_key($input, array_flip(['name', 'notes', 'attributes', 'resources', 'hp_current', 'hp_max', 'assigned_user_id', 'portrait_asset_id', 'is_public']));
                if (array_key_exists('notes', $fields)) $fields['notes'] ??= '';
                if (! $manager) {
                    abort_if(array_intersect(['assigned_user_id', 'portrait_asset_id', 'is_public'], array_keys($fields)), 403);
                }
                if (isset($fields['assigned_user_id'])) {
                    abort_unless(RoomMembership::query()->where('room_id', $room->id)->where('user_id', $fields['assigned_user_id'])->where('role', 'player')->exists(), 422);
                }
                if (isset($fields['portrait_asset_id'])) {
                    abort_unless(RoomAsset::query()->where('room_id', $room->id)->whereKey($fields['portrait_asset_id'])->exists(), 422);
                }
                abort_if(($fields['hp_current'] ?? $character->hp_current) > ($fields['hp_max'] ?? $character->hp_max), 422, 'PV atual deve estar entre zero e máximo.');
                foreach ($fields['resources'] ?? [] as $resource) {
                    abort_if($resource['current'] > $resource['max'], 422, 'Recurso atual excede máximo.');
                }
                if ($action === 'link') {
                    $token = RoomMapObject::query()->whereKey($input['token_id'])->whereHas('map', fn ($q) => $q->where('room_id', $room->id))->lockForUpdate()->firstOrFail();
                    abort_unless($token->layer->type === 'tokens' || $token->layer->type === 'gm', 422);
                    if ($token->revision !== $input['token_revision']) {
                        throw new ConflictHttpException('O token foi alterado. Atualize o mapa.');
                    }
                    $token->update(['character_id' => ($input['unlink'] ?? false) ? null : $character->id, 'revision' => $token->revision + 1]);
                    $character->increment('revision');
                } elseif ($action === 'delete') {
                    // Remove stale combat references and select the successor before deleting.
                    $combat = RoomCombat::query()->where('room_id', $room->id)->lockForUpdate()->first();
                    if ($combat) {
                        $this->removeParticipants($combat, fn ($p) => $p['character_id'] === $character->id);
                        $combat->revision++;
                        $combat->save();
                    }
                    RoomMapObject::query()->where('character_id', $character->id)->increment('revision');
                    $character->delete();
                } else {
                    $character->fill($fields);
                    if ($action !== 'create') $character->revision++;
                    $character->save();
                }
                $sync->record($room, 'room', 'characters.changed', null, null, $commandId, $key, []);

                return ['characterId' => $character->id, 'revision' => $character->revision];
            }));
    }

    public function combat(Request $request, Room $room, RoomSyncService $sync): JsonResponse
    {
        Gate::authorize('manage', $room);
        $input = $request->validate([
            'action' => ['required', Rule::in(['start', 'end', 'add', 'remove', 'initiative', 'next'])],
            'expected_revision' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'string', 'min:16', 'max:128'],
            'character_id' => ['required_if:action,add', 'integer'],
            'token_id' => ['sometimes', 'nullable', 'integer'],
            'participant_id' => ['required_if:action,remove,initiative', 'string', 'max:100'],
            'initiative' => ['required_if:action,add,initiative', 'integer', 'between:-1000000,1000000'],
        ]);

        return response()->json($sync->execute($room, $request->user(), 'combat-command', $input['idempotency_key'], $input,
            function (string $commandId) use ($room, $sync, $input) {
                $combat = RoomCombat::query()->firstOrCreate(['room_id' => $room->id]);
                if ($combat->revision !== $input['expected_revision']) {
                    throw new ConflictHttpException('O combate foi alterado. Atualize antes de continuar.');
                }
                $participants = $combat->participants;
                switch ($input['action']) {
                    case 'add':
                        abort_unless(RoomCharacter::query()->where('room_id', $room->id)->whereKey($input['character_id'])->exists(), 422);
                        abort_if(collect($participants)->contains('character_id', $input['character_id']), 422, 'Personagem já está no combate.');
                        if (isset($input['token_id'])) {
                            abort_unless(RoomMapObject::query()->whereKey($input['token_id'])->where('character_id', $input['character_id'])->whereHas('map', fn ($q) => $q->where('room_id', $room->id))->exists(), 422);
                        }
                        $participants[] = ['id' => (string) Str::uuid(), 'character_id' => $input['character_id'], 'token_id' => $input['token_id'] ?? null, 'initiative' => $input['initiative'], 'order' => count($participants) ? max(array_column($participants, 'order')) + 1 : 0];
                        break;
                    case 'initiative':
                        abort_unless(collect($participants)->contains('id', $input['participant_id']), 404);
                        foreach ($participants as &$participant) {
                            if ($participant['id'] === $input['participant_id']) $participant['initiative'] = $input['initiative'];
                        }
                        unset($participant);
                        break;
                    case 'remove':
                        abort_unless(collect($participants)->contains('id', $input['participant_id']), 404);
                        $this->removeParticipants($combat, fn ($p) => $p['id'] === $input['participant_id']);
                        $participants = $combat->participants;
                        break;
                    case 'start':
                        abort_if($combat->active, 422, 'Combate já iniciado.');
                        $combat->active = true;
                        $combat->round = count($participants) ? 1 : 0;
                        $combat->active_id = null;
                        break;
                    case 'end':
                        $combat->active = false;
                        $combat->active_id = null;
                        $combat->round = 0;
                        break;
                    case 'next':
                        abort_unless($combat->active && count($participants), 422, 'Inicie um combate com participantes.');
                        $index = array_search($combat->active_id, array_column($participants, 'id'), true);
                        $next = $index === false ? 0 : ($index + 1) % count($participants);
                        if ($index !== false && $next === 0) $combat->round++;
                        $combat->active_id = $participants[$next]['id'];
                        break;
                }
                usort($participants, fn ($a, $b) => [$b['initiative'], $a['order']] <=> [$a['initiative'], $b['order']]);
                $combat->participants = $participants;
                if ($combat->active && ! $combat->active_id && count($participants)) {
                    $combat->active_id = $participants[0]['id'];
                    $combat->round = max(1, $combat->round);
                }
                $combat->revision++;
                $combat->save();
                $sync->record($room, 'room', 'characters.changed', null, null, $commandId, $input['idempotency_key'], []);

                return ['revision' => $combat->revision];
            }));
    }

    private function removeParticipants(RoomCombat $combat, callable $remove): void
    {
        $old = $combat->participants;
        $remaining = array_values(array_filter($old, fn ($p) => ! $remove($p)));
        if (! collect($remaining)->contains('id', $combat->active_id)) {
            $index = array_search($combat->active_id, array_column($old, 'id'), true);
            $successor = null;
            if ($index !== false) {
                for ($offset = 1; $offset <= count($old); $offset++) {
                    $candidate = $old[($index + $offset) % count($old)];
                    if (! $remove($candidate)) {
                        $successor = $candidate['id'];
                        if ($index + $offset >= count($old)) $combat->round++;
                        break;
                    }
                }
            }
            $combat->active_id = $successor;
        }
        if (! count($remaining)) {
            $combat->active_id = null;
            $combat->round = 0;
        }
        $combat->participants = $remaining;
    }
}
