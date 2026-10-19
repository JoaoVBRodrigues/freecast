<?php

namespace Database\Seeders;

use App\Models\Room;
use App\Models\RoomMembership;
use App\Models\RoomMessage;
use App\Models\RoomFavorite;
use App\Models\RoomVisit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestFixtureSeeder extends Seeder
{
    public function run(): void
    {
        \App\Support\TestEnvironmentGuard::assert();
        $mestre = User::query()->updateOrCreate(
            ['email' => 'mestre@freecast.test'],
            ['name' => 'Nara Vale', 'password' => Hash::make('Mesa2026!')],
        );
        $jogador = User::query()->updateOrCreate(
            ['email' => 'jogador@freecast.test'],
            ['name' => 'Theo Arantes', 'password' => Hash::make('Mesa2026!')],
        );
        $convidada = User::query()->updateOrCreate(
            ['email' => 'convidada@freecast.test'],
            ['name' => 'Lia Monte', 'password' => Hash::make('Mesa2026!')],
        );

        $mesa = Room::query()->updateOrCreate(
            ['title' => 'A Cripta das Marés', 'owner_id' => $mestre->id],
            [
                'system' => 'D&D 5ª Edição',
                'description' => 'A névoa esconde ruínas antigas na costa. O grupo se reúne ao cair da noite.',
                'is_public' => true,
                'password_hash' => null,
            ],
        );

        foreach ([
            [$mestre, RoomMembership::ROLE_OWNER],
            [$jogador, RoomMembership::ROLE_PLAYER],
            [$convidada, RoomMembership::ROLE_PLAYER],
        ] as [$user, $role]) {
            $mesa->memberships()->updateOrCreate(
                ['user_id' => $user->id],
                ['role' => $role],
            );
        }

        RoomMessage::query()->firstOrCreate(
            ['room_id' => $mesa->id, 'user_id' => $mestre->id, 'body' => 'As lanternas tremem quando a porta de pedra se abre.'],
        );
        RoomMessage::query()->firstOrCreate(
            ['room_id' => $mesa->id, 'user_id' => $jogador->id, 'body' => 'Theo observa as marcas no arco antes de entrar.'],
        );

        RoomFavorite::query()->firstOrCreate(['room_id' => $mesa->id, 'user_id' => $jogador->id]);
        RoomVisit::query()->updateOrCreate(
            ['room_id' => $mesa->id, 'user_id' => $jogador->id],
            ['seen_at' => now()],
        );
    }
}
