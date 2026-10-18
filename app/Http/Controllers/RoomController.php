<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\RoomFavorite;
use App\Models\RoomMembership;
use App\Models\RoomMessage;
use App\Models\RoomPreference;
use App\Models\RoomVisit;
use App\Models\RoomMacro;
use App\Models\RoomAsset;
use App\Services\ReverbConnectionTerminator;
use App\Support\RoomCardData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class RoomController extends Controller
{
    private const DEFAULT_PANELS = [
        'library' => true,
        'macros' => true,
        'combat' => true,
        'participants' => true,
    ];

    public function index(Request $request): Response
    {
        $user = $request->user();
        $rooms = Room::query()
            ->where(function ($query) use ($user) {
                $query->where('is_public', true)
                    ->orWhereHas('memberships', fn ($membership) => $membership->where('user_id', $user->id));
            })
            ->with(['owner:id,name', 'memberships', 'favorites'])
            ->withCount('members')
            ->latest()
            ->get()
            ->map(fn (Room $room) => RoomCardData::from($room, $user));

        return Inertia::render('Rooms/Index', [
            'rooms' => $rooms,
            'section' => $request->string('section')->toString() ?: 'online',
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Rooms/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:80'],
            'system' => ['required', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:600'],
            'is_public' => ['required', 'boolean'],
            'password' => ['nullable', 'required_if:is_public,0', 'string', 'min:6', 'max:100'],
        ]);

        $room = DB::transaction(function () use ($request, $data) {
            $room = Room::query()->create([
                'owner_id' => $request->user()->id,
                'title' => $data['title'],
                'system' => $data['system'],
                'description' => $data['description'] ?? '',
                'is_public' => $data['is_public'],
                'password_hash' => $data['is_public'] ? null : Hash::make($data['password']),
            ]);

            $room->memberships()->create([
                'user_id' => $request->user()->id,
                'role' => RoomMembership::ROLE_OWNER,
            ]);

            return $room;
        });

        return to_route('rooms.show', $room);
    }

    public function show(Request $request, Room $room): Response
    {
        Gate::authorize('view', $room);

        RoomVisit::query()->updateOrCreate(
            ['room_id' => $room->id, 'user_id' => $request->user()->id],
            ['seen_at' => now()],
        );

        $preference = RoomPreference::query()->firstOrCreate(
            ['room_id' => $room->id, 'user_id' => $request->user()->id],
            ['panels' => self::DEFAULT_PANELS],
        );

        $room->load([
            'owner:id,name',
            'memberships.user:id,name',
        ]);

        $messages = $room->messages()
            ->with(['user:id,name', 'recipient:id,name'])
            ->where(function ($query) use ($request) {
                $query->where('message_type', '!=', 'private')
                    ->orWhere('user_id', $request->user()->id)
                    ->orWhere('recipient_user_id', $request->user()->id);
            })
            ->latest('id')
            ->limit(50)
            ->get()
            ->reverse()
            ->values();

        $macros = RoomMacro::query()
            ->where(function ($query) use ($room, $request) {
                $query->where(fn ($personal) => $personal
                    ->where('user_id', $request->user()->id)
                    ->where(fn ($scope) => $scope
                        ->where('scope', RoomMacro::PERSONAL_GLOBAL)
                        ->orWhere(fn ($inRoom) => $inRoom->where('scope', RoomMacro::PERSONAL_ROOM)->where('room_id', $room->id))))
                    ->orWhere(fn ($shared) => $shared->where('scope', RoomMacro::SHARED_ROOM)->where('room_id', $room->id)->whereNull('user_id'));
            })
            ->orderBy('scope')
            ->orderBy('name')
            ->get(['id', 'scope', 'name', 'body', 'revision'])
            ->map(fn (RoomMacro $macro) => [
                'id' => $macro->id,
                'scope' => $macro->scope,
                'name' => $macro->name,
                'body' => $macro->body,
                'revision' => $macro->revision,
            ]);

        $libraryAssets = $room->assets()
            ->with('folder')
            ->where('is_master_only', false)
            ->orderBy('name')
            ->get()
            ->filter(fn (RoomAsset $asset) => ! $asset->folder?->is_master_only)
            ->map(fn (RoomAsset $asset) => [
                'id' => $asset->id,
                'name' => $asset->name,
                'url' => route('maps.assets.show', [$room, $asset]),
            ])
            ->values();

        return Inertia::render('Rooms/Show', [
            'room' => [
                'id' => $room->id,
                'title' => $room->title,
                'system' => $room->system,
                'description' => $room->description,
                'owner_name' => $room->owner->name,
                'is_owner' => $room->owner_id === $request->user()->id,
                'active_map_id' => $room->active_map_id,
                'active_map_title' => $room->active_map_id ? $room->maps()->whereKey($room->active_map_id)->value('title') : null,
            ],
            'user_id' => $request->user()->id,
            'members' => $room->memberships
                ->map(fn (RoomMembership $membership) => [
                    'id' => $membership->user_id,
                    'name' => $membership->user->name,
                    'role' => $membership->role,
                ])
                ->values(),
            'messages' => $messages->map(fn (RoomMessage $message) => [
                    'id' => $message->id,
                    'author_id' => $message->user_id,
                    'author' => $message->user->name,
                    'recipient_id' => $message->message_type === 'private' ? $message->recipient_user_id : null,
                    'recipient' => $message->message_type === 'private' ? $message->recipient?->name : null,
                    'message_type' => $message->message_type,
                    'body' => $message->body,
                    'assets' => RoomAsset::query()
                        ->with('folder')
                        ->where('room_id', $room->id)
                        ->whereIn('id', $message->asset_ids ?? [])
                        ->get()
                        ->filter(fn (RoomAsset $asset) => Gate::allows('manage', $room) || (! $asset->is_master_only && ! $asset->folder?->is_master_only))
                        ->map(fn (RoomAsset $asset) => [
                            'id' => $asset->id,
                            'name' => $asset->name,
                            'url' => route('maps.assets.show', [$room, $asset]),
                        ])
                        ->values(),
                    'created_at' => $message->created_at->toIso8601String(),
                ]),
            'macros' => $macros,
            'library_assets' => $libraryAssets,
            'can_manage' => Gate::allows('manage', $room),
            'panels' => array_merge(self::DEFAULT_PANELS, $preference->panels ?? []),
        ]);
    }

    public function join(Request $request, Room $room): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['nullable', 'string', 'max:100'],
        ]);

        if (! $room->is_public && (! isset($data['password']) || ! Hash::check($data['password'], $room->password_hash))) {
            abort(403);
        }

        $room->memberships()->firstOrCreate(
            ['user_id' => $request->user()->id],
            ['role' => RoomMembership::ROLE_PLAYER],
        );

        return to_route('rooms.show', $room);
    }

    public function leave(Request $request, Room $room): RedirectResponse
    {
        Gate::authorize('leave', $room);
        $room->memberships()->where('user_id', $request->user()->id)->delete();
        app(ReverbConnectionTerminator::class)->terminate($request->user()->id);

        return to_route('rooms.index')->with('status', 'Você saiu da mesa.');
    }

    public function removeMember(Request $request, Room $room, int $memberId): RedirectResponse
    {
        Gate::authorize('manage', $room);
        abort_if($memberId === $room->owner_id, 403);
        $membership = $room->memberships()->where('user_id', $memberId)->firstOrFail();
        $membership->delete();
        app(ReverbConnectionTerminator::class)->terminate($memberId);

        return back()->with('status', 'O acesso do participante foi revogado.');
    }

    public function toggleFavorite(Request $request, Room $room): RedirectResponse
    {
        Gate::authorize('favorite', $room);

        $favorite = RoomFavorite::query()->firstOrNew([
            'room_id' => $room->id,
            'user_id' => $request->user()->id,
        ]);

        if ($favorite->exists) {
            $favorite->delete();
        } else {
            $favorite->save();
        }

        return back();
    }

    public function updatePreferences(Request $request, Room $room): RedirectResponse
    {
        Gate::authorize('view', $room);

        $data = $request->validate([
            'panels' => ['required', 'array'],
            'panels.library' => ['sometimes', 'boolean'],
            'panels.macros' => ['sometimes', 'boolean'],
            'panels.combat' => ['sometimes', 'boolean'],
            'panels.participants' => ['sometimes', 'boolean'],
        ]);

        $current = RoomPreference::query()->firstOrCreate(
            ['room_id' => $room->id, 'user_id' => $request->user()->id],
            ['panels' => self::DEFAULT_PANELS],
        );

        $panels = array_merge(self::DEFAULT_PANELS, $current->panels ?? [], $data['panels']);
        $current->update(['panels' => $panels]);

        return back();
    }

    public function storeMessage(Request $request, Room $room): RedirectResponse
    {
        Gate::authorize('view', $room);
        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        abort(410, 'Use o endpoint de mensagens em tempo real.');
    }
}
