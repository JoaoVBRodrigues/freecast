<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_characters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('portrait_asset_id')->nullable()->constrained('room_assets')->nullOnDelete();
            $table->string('name', 100);
            $table->text('notes')->default('');
            $table->json('attributes')->default('[]');
            $table->json('resources')->default('[]');
            $table->integer('hp_current')->default(0);
            $table->integer('hp_max')->default(0);
            $table->boolean('is_public')->default(false);
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
            $table->index(['room_id', 'assigned_user_id']);
        });
        Schema::table('room_map_objects', function (Blueprint $table) {
            $table->foreignId('character_id')->nullable()->constrained('room_characters')->nullOnDelete();
        });
        Schema::create('room_combats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('active')->default(false);
            $table->unsignedInteger('round')->default(0);
            $table->string('active_id')->nullable();
            $table->json('participants')->default('[]');
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_combats');
        Schema::table('room_map_objects', fn (Blueprint $table) => $table->dropConstrainedForeignId('character_id'));
        Schema::dropIfExists('room_characters');
    }
};
