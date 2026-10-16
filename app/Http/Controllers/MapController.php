<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\RoomAsset;
use App\Models\RoomMap;
use App\Models\RoomMapLayer;
use App\Models\RoomMapObject;
use App\Models\RoomMembership;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MapController extends Controller
{
    private const LAYERS = [
        ['type' => 'background', 'name' => 'Fundo', 'sort_order' => 0, 'locked' => true, 'is_master_only' => false],
        ['type' => 'objects', 'name' => 'Objetos', 'sort_order' => 1, 'locked' => false, 'is_master_only' => false],
        ['type' => 'tokens', 'name' => 'Tokens', 'sort_order' => 2, 'locked' => false, 'is_master_only' => false],
        ['type' => 'gm', 'name' => 'Somente mestre', 'sort_order' => 3, 'locked' => false, 'is_master_only' => true],
    ];

    public function index(Request $request, Room $room): Response
    {
        $this->authorizeRoom($request, $room, 'view');
        $canManage = Gate::allows('manage', $room);

        return Inertia::render('Maps/Index', [
            'room' => $this->roomData($room),
            'can_manage' => $canManage,
            'maps' => $room->maps()->with('background')->latest()->get()->map(fn (RoomMap $map) => [
                'id' => $map->id,
                'title' => $map->title,
                'width' => $map->width,
                'height' => $map->height,
                'background' => $map->background && ! $this->assetIsMasterOnly($map->background)
                    ? route('maps.assets.show', [$room, $map->background])
                    : null,
                'updated_at' => $map->updated_at->toIso8601String(),
            ]),
            'folders' => $room->assetFolders()
                ->when(! $canManage, fn ($query) => $query->where('is_master_only', false))
                ->orderBy('name')
                ->get(['id', 'parent_id', 'name', 'is_master_only']),
            'assets' => $this->assetData($room, $canManage),
        ]);
    }

    public function store(Request $request, Room $room): RedirectResponse
    {
        $this->authorizeRoom($request, $room, 'manage');
        $data = $request->validate([
            'title' => ['required', 'string', 'max:100', Rule::unique('room_maps', 'title')->where('room_id', $room->id)],
            'width' => ['required', 'integer', 'min:200', 'max:8000'],
            'height' => ['required', 'integer', 'min:200', 'max:8000'],
            'cell_size' => ['required', 'integer', 'min:8', 'max:256'],
        ]);

        $map = DB::transaction(function () use ($request, $room, $data) {
            $map = $room->maps()->create([
                ...$data,
                'created_by' => $request->user()->id,
            ]);

            foreach (self::LAYERS as $layer) {
                $map->layers()->create($layer);
            }

            return $map;
        });

        return to_route('maps.show', [$room, $map]);
    }

    public function show(Request $request, Room $room, RoomMap $map): Response
    {
        $this->authorizeRoom($request, $room, 'view');
        $this->assertMapBelongsToRoom($room, $map);

        $canManage = Gate::allows('manage', $room);
        $map->load([
            'background.folder',
            'layers.objects.asset.folder',
        ]);

        $layers = $map->layers
            ->filter(fn (RoomMapLayer $layer) => $canManage || (! $layer->is_master_only && $layer->visible))
            ->map(function (RoomMapLayer $layer) use ($room, $request, $canManage) {
                $objects = $layer->objects
                    ->filter(function (RoomMapObject $object) use ($canManage, $room) {
                        if ($canManage) {
                            return true;
                        }

                        return $object->visible
                            && ! $this->assetIsMasterOnly($object->asset)
                            && $object->asset->room_id === $room->id;
                    })
                    ->sortBy('z_index')
                    ->values()
                    ->map(fn (RoomMapObject $object) => $this->objectData($object, $request, $room, $canManage));

                return [
                    'id' => $layer->id,
                    'type' => $layer->type,
                    'name' => $layer->name,
                    'sort_order' => $layer->sort_order,
                    'visible' => $layer->visible,
                    'locked' => $layer->locked,
                    'objects' => $objects,
                ];
            })
            ->values();

        $assignableMembers = $canManage
            ? $room->memberships()
                ->with('user:id,name')
                ->where('role', RoomMembership::ROLE_PLAYER)
                ->get()
                ->map(fn (RoomMembership $membership) => [
                    'id' => $membership->user_id,
                    'name' => $membership->user->name,
                ])
                ->values()
            : collect();

        return Inertia::render('Maps/Editor', [
            'room' => $this->roomData($room),
            'map' => [
                'id' => $map->id,
                'title' => $map->title,
                'width' => $map->width,
                'height' => $map->height,
                'cell_size' => $map->cell_size,
                'grid_color' => $map->grid_color,
                'grid_opacity' => $map->grid_opacity,
                'grid_enabled' => $map->grid_enabled,
                'background_asset_id' => $map->background_asset_id,
                'background_url' => $map->background && ! $this->assetIsMasterOnly($map->background)
                    ? route('maps.assets.show', [$room, $map->background])
                    : null,
            ],
            'can_manage' => $canManage,
            'user_id' => $request->user()->id,
            'layers' => $layers,
            'assets' => $this->assetData($room, $canManage),
            'assignable_members' => $assignableMembers,
        ]);
    }

    public function update(Request $request, Room $room, RoomMap $map): RedirectResponse
    {
        $this->authorizeMapManager($request, $room, $map);
        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('room_maps', 'title')->where('room_id', $room->id)->ignore($map->id)],
            'width' => ['sometimes', 'required', 'integer', 'min:200', 'max:8000'],
            'height' => ['sometimes', 'required', 'integer', 'min:200', 'max:8000'],
            'cell_size' => ['sometimes', 'required', 'integer', 'min:8', 'max:256'],
            'grid_color' => ['sometimes', 'required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'grid_opacity' => ['sometimes', 'required', 'numeric', 'min:0.05', 'max:1'],
            'grid_enabled' => ['sometimes', 'required', 'boolean'],
            'background_asset_id' => ['sometimes', 'nullable', 'integer', 'exists:room_assets,id'],
        ]);

        if (array_key_exists('background_asset_id', $data) && $data['background_asset_id'] !== null) {
            $asset = RoomAsset::query()->with('folder')->findOrFail($data['background_asset_id']);
            abort_unless(
                $asset->room_id === $room->id
                    && $asset->kind === RoomAsset::KIND_BACKGROUND
                    && ! $this->assetIsMasterOnly($asset),
                422,
            );
        }

        $map->update($data);

        return back();
    }

    public function duplicate(Request $request, Room $room, RoomMap $map): RedirectResponse
    {
        $this->authorizeMapManager($request, $room, $map);
        $map->load(['layers.objects']);

        $copy = DB::transaction(function () use ($request, $room, $map) {
            $baseTitle = $map->title.' (cópia)';
            $title = $baseTitle;
            $suffix = 2;
            while ($room->maps()->where('title', $title)->exists()) {
                $title = $baseTitle.' '.$suffix++;
            }

            $copy = $room->maps()->create([
                'background_asset_id' => $map->background_asset_id,
                'created_by' => $request->user()->id,
                'title' => $title,
                'width' => $map->width,
                'height' => $map->height,
                'cell_size' => $map->cell_size,
                'grid_color' => $map->grid_color,
                'grid_opacity' => $map->grid_opacity,
                'grid_enabled' => $map->grid_enabled,
            ]);

            $layerIds = [];
            foreach ($map->layers as $layer) {
                $newLayer = $copy->layers()->create($layer->only([
                    'type', 'name', 'sort_order', 'visible', 'locked', 'is_master_only',
                ]));
                $layerIds[$layer->id] = $newLayer->id;

                foreach ($layer->objects as $object) {
                    $newLayer->objects()->create([
                        'room_map_id' => $copy->id,
                        'room_asset_id' => $object->room_asset_id,
                        'assigned_user_id' => $object->assigned_user_id,
                        'name' => $object->name,
                        'x' => $object->x,
                        'y' => $object->y,
                        'width' => $object->width,
                        'height' => $object->height,
                        'rotation' => $object->rotation,
                        'z_index' => $object->z_index,
                        'visible' => $object->visible,
                        'locked' => $object->locked,
                    ]);
                }
            }

            return $copy;
        });

        return to_route('maps.show', [$room, $copy]);
    }

    public function destroy(Request $request, Room $room, RoomMap $map): RedirectResponse
    {
        $this->authorizeMapManager($request, $room, $map);
        $map->delete();

        return to_route('maps.index', $room);
    }

    public function updateLayer(Request $request, Room $room, RoomMap $map, RoomMapLayer $layer): RedirectResponse
    {
        $this->authorizeMapManager($request, $room, $map);
        $this->assertLayerBelongsToMap($map, $layer);
        $data = $request->validate([
            'visible' => ['sometimes', 'boolean'],
            'locked' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:20'],
        ]);

        if (array_key_exists('sort_order', $data)) {
            DB::transaction(function () use ($layer, $data) {
                $oldOrder = (int) $layer->sort_order;
                $newOrder = (int) $data['sort_order'];

                if ($newOrder < $oldOrder) {
                    $layer->map->layers()
                        ->where('id', '!=', $layer->id)
                        ->whereBetween('sort_order', [$newOrder, $oldOrder - 1])
                        ->increment('sort_order');
                } elseif ($newOrder > $oldOrder) {
                    $layer->map->layers()
                        ->where('id', '!=', $layer->id)
                        ->whereBetween('sort_order', [$oldOrder + 1, $newOrder])
                        ->decrement('sort_order');
                }

                $layer->update([
                    ...array_diff_key($data, ['sort_order' => true]),
                    'sort_order' => $newOrder,
                ]);
            });
        } else {
            $layer->update($data);
        }

        return back();
    }

    public function storeObject(Request $request, Room $room, RoomMap $map): RedirectResponse
    {
        $this->authorizeMapManager($request, $room, $map);
        $data = $request->validate([
            'asset_id' => ['required', 'integer', 'exists:room_assets,id'],
            'layer_id' => ['required', 'integer', 'exists:room_map_layers,id'],
            'x' => ['required', 'numeric', 'between:-100000,100000'],
            'y' => ['required', 'numeric', 'between:-100000,100000'],
            'assigned_user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ]);

        $asset = RoomAsset::query()->with('folder')->findOrFail($data['asset_id']);
        abort_unless($asset->room_id === $room->id && $asset->kind === RoomAsset::KIND_TOKEN, 404);
        $layer = RoomMapLayer::query()->findOrFail($data['layer_id']);
        $this->assertLayerBelongsToMap($map, $layer);
        abort_if($layer->type === 'background', 422);

        if ($this->assetIsMasterOnly($asset) && ! $layer->is_master_only) {
            throw ValidationException::withMessages([
                'asset_id' => 'Recursos exclusivos do mestre só podem ficar na camada exclusiva do mestre.',
            ]);
        }

        if ($layer->is_master_only && ! Gate::allows('manage', $room)) {
            abort(403);
        }

        $assignedUserId = $data['assigned_user_id'] ?? null;
        if ($assignedUserId !== null) {
            $validAssignee = $room->memberships()
                ->where('user_id', $assignedUserId)
                ->where('role', RoomMembership::ROLE_PLAYER)
                ->exists();
            abort_unless($validAssignee, 422);
        }

        $sizeRatio = min(1, 512 / max($asset->width, $asset->height));
        $object = $layer->objects()->create([
            'room_map_id' => $map->id,
            'room_asset_id' => $asset->id,
            'assigned_user_id' => $assignedUserId,
            'name' => $asset->name,
            'x' => $data['x'],
            'y' => $data['y'],
            'width' => max(8, (int) round($asset->width * $sizeRatio)),
            'height' => max(8, (int) round($asset->height * $sizeRatio)),
            'z_index' => (int) $map->objects()->max('z_index') + 1,
        ]);

        return back()->with('map_object_id', $object->id);
    }

    public function updateObject(Request $request, Room $room, RoomMap $map, RoomMapObject $object): RedirectResponse
    {
        $this->authorizeRoom($request, $room, 'view');
        $this->assertMapBelongsToRoom($room, $map);
        $this->assertObjectBelongsToMap($map, $object);

        $canManage = Gate::allows('manage', $room);
        $object->load(['layer', 'asset.folder']);

        if (! $canManage) {
            $keys = array_diff(array_keys($request->all()), ['x', 'y']);
            abort_if($keys !== [], 403);
            abort_unless(
                $object->assigned_user_id === $request->user()->id
                    && $object->visible
                    && ! $object->locked
                    && $object->layer->type === 'tokens'
                    && $object->layer->visible
                    && ! $object->layer->locked
                    && ! $object->layer->is_master_only
                    && ! $this->assetIsMasterOnly($object->asset),
                403,
            );
            $data = $request->validate([
                'x' => ['required', 'numeric', 'between:-100000,100000'],
                'y' => ['required', 'numeric', 'between:-100000,100000'],
            ]);
        } else {
            $data = $request->validate([
                'name' => ['sometimes', 'required', 'string', 'max:120'],
                'x' => ['sometimes', 'required', 'numeric', 'between:-100000,100000'],
                'y' => ['sometimes', 'required', 'numeric', 'between:-100000,100000'],
                'width' => ['sometimes', 'required', 'integer', 'min:8', 'max:2048'],
                'height' => ['sometimes', 'required', 'integer', 'min:8', 'max:2048'],
                'rotation' => ['sometimes', 'required', 'numeric', 'between:-3600,3600'],
                'visible' => ['sometimes', 'boolean'],
                'locked' => ['sometimes', 'boolean'],
                'z_index' => ['sometimes', 'integer', 'between:-10000,10000'],
                'assigned_user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
                'layer_id' => ['sometimes', 'integer', 'exists:room_map_layers,id'],
            ]);

            if (isset($data['layer_id'])) {
                $layer = RoomMapLayer::query()->findOrFail($data['layer_id']);
                $this->assertLayerBelongsToMap($map, $layer);
                abort_if($layer->type === 'background', 422);
                if ($this->assetIsMasterOnly($object->asset) && ! $layer->is_master_only) {
                    throw ValidationException::withMessages([
                        'layer_id' => 'Recursos exclusivos do mestre só podem ficar na camada exclusiva do mestre.',
                    ]);
                }
                $data['room_map_layer_id'] = $data['layer_id'];
                unset($data['layer_id']);
            }

            if (array_key_exists('assigned_user_id', $data) && $data['assigned_user_id'] !== null) {
                $validAssignee = $room->memberships()
                    ->where('user_id', $data['assigned_user_id'])
                    ->where('role', RoomMembership::ROLE_PLAYER)
                    ->exists();
                abort_unless($validAssignee, 422);
            }
        }

        $object->update($data);

        return back();
    }

    public function destroyObject(Request $request, Room $room, RoomMap $map, RoomMapObject $object): RedirectResponse
    {
        $this->authorizeMapManager($request, $room, $map);
        $this->assertObjectBelongsToMap($map, $object);
        $object->delete();

        return back();
    }

    private function authorizeMapManager(Request $request, Room $room, RoomMap $map): void
    {
        $this->authorizeRoom($request, $room, 'manage');
        $this->assertMapBelongsToRoom($room, $map);
    }

    private function authorizeRoom(Request $request, Room $room, string $ability): void
    {
        Gate::authorize($ability, $room);
    }

    private function assertMapBelongsToRoom(Room $room, RoomMap $map): void
    {
        abort_unless($map->room_id === $room->id, 404);
    }

    private function assertLayerBelongsToMap(RoomMap $map, RoomMapLayer $layer): void
    {
        abort_unless($layer->room_map_id === $map->id, 404);
    }

    private function assertObjectBelongsToMap(RoomMap $map, RoomMapObject $object): void
    {
        abort_unless($object->room_map_id === $map->id, 404);
    }

    private function assetIsMasterOnly(RoomAsset $asset): bool
    {
        return $asset->is_master_only || (bool) $asset->folder?->is_master_only;
    }

    private function roomData(Room $room): array
    {
        return [
            'id' => $room->id,
            'title' => $room->title,
        ];
    }

    private function assetData(Room $room, bool $canManage): array
    {
        return $room->assets()
            ->with('folder')
            ->when(! $canManage, fn ($query) => $query->where('is_master_only', false))
            ->orderBy('name')
            ->get()
            ->filter(fn (RoomAsset $asset) => $canManage || ! $this->assetIsMasterOnly($asset))
            ->map(fn (RoomAsset $asset) => [
                'id' => $asset->id,
                'name' => $asset->name,
                'kind' => $asset->kind,
                'width' => $asset->width,
                'height' => $asset->height,
                'folder_id' => $asset->folder_id,
                'is_master_only' => $this->assetIsMasterOnly($asset),
                'url' => route('maps.assets.show', [$room, $asset]),
            ])
            ->values()
            ->all();
    }

    private function objectData(RoomMapObject $object, Request $request, Room $room, bool $canManage): array
    {
        $assetIsMasterOnly = $this->assetIsMasterOnly($object->asset);
        $canMove = $canManage || (
            $object->assigned_user_id === $request->user()->id
                && $object->visible
                && ! $object->locked
                && $object->layer->type === 'tokens'
                && $object->layer->visible
                && ! $object->layer->locked
                && ! $object->layer->is_master_only
                && ! $assetIsMasterOnly
        );

        return [
            'id' => $object->id,
            'layer_id' => $object->room_map_layer_id,
            'asset_id' => $object->room_asset_id,
            'name' => $object->name,
            'x' => $object->x,
            'y' => $object->y,
            'width' => $object->width,
            'height' => $object->height,
            'rotation' => $object->rotation,
            'z_index' => $object->z_index,
            'visible' => $object->visible,
            'locked' => $object->locked,
            'assigned_user_id' => $canManage || $object->assigned_user_id === $request->user()->id
                ? $object->assigned_user_id
                : null,
            'can_move' => $canMove,
            'asset_name' => $object->asset->name,
            'url' => $assetIsMasterOnly
                ? ($canManage ? route('maps.assets.show', [$room, $object->asset]) : null)
                : route('maps.assets.show', [$room, $object->asset]),
        ];
    }
}
