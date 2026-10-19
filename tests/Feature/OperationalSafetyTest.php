<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\User;
use App\Support\TestEnvironmentGuard;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\TestFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OperationalSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_reexecution_preserves_existing_accounts_room_and_relations(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::where('email', 'mestre@freecast.test')->firstOrFail();
        $user->update(['name' => 'Nome preservado', 'password' => 'SenhaSinteticaSomenteTeste!']);
        $room = Room::firstOrFail();
        $room->update(['is_public' => false, 'password_hash' => 'synthetic-test-hash', 'system' => 'Customizado', 'description' => 'Preservar']);
        $room->memberships()->where('user_id', '!=', $user->id)->delete();
        $room->messages()->delete();
        $before = [];
        foreach (['users', 'rooms', 'room_memberships', 'room_messages', 'room_favorites', 'room_visits'] as $table) {
            $before[$table] = DB::table($table)->get()->toJson();
        }
        $this->seed(DatabaseSeeder::class);
        foreach ($before as $table => $rows) {
            $this->assertSame($rows, DB::table($table)->get()->toJson(), $table);
        }
    }

    public function test_test_seeder_refuses_wrong_effective_database_before_any_write(): void
    {
        $connection = \Mockery::mock();
        $connection->shouldReceive('getDriverName')->once()->andReturn('pgsql');
        $connection->shouldReceive('selectOne')->once()->andReturn((object) ['name' => 'freecast']);
        $connection->shouldNotReceive('insert');
        $connection->shouldNotReceive('statement');
        DB::shouldReceive('connection')->once()->andReturn($connection);
        $this->expectException(\RuntimeException::class);
        (new TestFixtureSeeder)->run();
    }

    public function test_guard_refuses_development_storage_without_writing(): void
    {
        config(['filesystems.disks.local.root' => storage_path('app/private')]);
        $this->expectException(\RuntimeException::class);
        TestEnvironmentGuard::assert();
    }

    public function test_guard_refuses_non_postgresql_connection_without_query_or_write(): void
    {
        $connection = \Mockery::mock();
        $connection->shouldReceive('getDriverName')->once()->andReturn('sqlite');
        $connection->shouldNotReceive('selectOne');
        DB::shouldReceive('connection')->once()->andReturn($connection);
        $this->expectException(\RuntimeException::class);
        TestEnvironmentGuard::assert();
    }
}
