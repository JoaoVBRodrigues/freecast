<?php

namespace App\Http\Controllers;

use App\Events\RoomMessagePosted;
use App\Models\Room;
use App\Models\RoomFavorite;
use App\Models\RoomMembership;
use App\Models\RoomMessage;
use App\Models\RoomPreference;
use App\Models\RoomVisit;
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
            'messages' => fn ($query) => $query->with('user:id,name')->latest()->limit(60),
        ]);

        return Inertia::render('Rooms/Show', [
            'room' => [
                'id' => $room->id,
                'title' => $room->title,
                'system' => $room->system,
                'description' => $room->description,
                'owner_name' => $room->owner->name,
                'is_owner' => $room->owner_id === $request->user()->id,
            ],
            'members' => $room->memberships
                ->map(fn (RoomMembership $membership) => [
                    'id' => $membership->user_id,
                    'name' => $membership->user->name,
                    'role' => $membership->role,
                ])
                ->values(),
            'messages' => $room->messages
                ->sortBy('created_at')
                ->values()
                ->map(fn (RoomMessage $message) => [
                    'id' => $message->id,
                    'author' => $message->user->name,
                    'body' => $message->body,
                    'created_at' => $message->created_at->toIso8601String(),
                ]),
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

        return to_route('rooms.index')->with('status', 'Você saiu da mesa.');
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

        $message = $room->messages()->create([
            'user_id' => $request->user()->id,
            'body' => trim($data['body']),
        ]);

        broadcast(new RoomMessagePosted($message->load('user:id,name')))->toOthers();

        return back();
    }
}
