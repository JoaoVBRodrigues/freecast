<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\RoomFavorite;
use App\Models\RoomVisit;
use App\Support\RoomCardData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $roomCardRelations = ['owner:id,name', 'memberships', 'favorites'];

        $recent = RoomVisit::query()
            ->where('user_id', $user->id)
            ->with(['room' => fn ($query) => $query->with($roomCardRelations)->withCount('members')])
            ->orderByDesc('seen_at')
            ->limit(3)
            ->get()
            ->pluck('room')
            ->filter()
            ->map(fn (Room $room) => RoomCardData::from($room, $user))
            ->values();

        $favorites = RoomFavorite::query()
            ->where('user_id', $user->id)
            ->with(['room' => fn ($query) => $query->with($roomCardRelations)->withCount('members')])
            ->latest()
            ->limit(3)
            ->get()
            ->pluck('room')
            ->filter()
            ->map(fn (Room $room) => RoomCardData::from($room, $user))
            ->values();

        $owned = Room::query()
            ->where('owner_id', $user->id)
            ->with($roomCardRelations)
            ->withCount('members')
            ->latest()
            ->limit(3)
            ->get()
            ->map(fn (Room $room) => RoomCardData::from($room, $user));

        $featured = Room::query()
            ->where('is_public', true)
            ->with($roomCardRelations)
            ->withCount('members')
            ->latest()
            ->first();

        return Inertia::render('Home', [
            'featured' => $featured ? RoomCardData::from($featured, $user) : null,
            'recent' => $recent,
            'favorites' => $favorites,
            'owned' => $owned,
        ]);
    }
}
