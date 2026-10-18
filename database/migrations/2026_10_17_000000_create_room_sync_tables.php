<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->foreignId('active_map_id')->nullable()->constrained('room_maps')->nullOnDelete();
        });

        Schema::table('room_map_objects', function (Blueprint $table) {
            $table->unsignedInteger('revision')->default(1);
        });

        Schema::table('room_maps', function (Blueprint $table) {
            $table->unsignedInteger('revision')->default(1);
        });

        Schema::table('rooms', function (Blueprint $table) {
            $table->unsignedInteger('revision')->default(1);
        });

        Schema::table('room_map_layers', function (Blueprint $table) {
            $table->unsignedInteger('revision')->default(1);
        });

        Schema::table('room_messages', function (Blueprint $table) {
            $table->string('message_type', 16)->default('public');
            $table->foreignId('recipient_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('asset_ids')->nullable();
            $table->index(['room_id', 'recipient_user_id', 'id']);
        });

        Schema::create('room_event_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->string('audience', 48);
            $table->unsignedBigInteger('sequence')->default(0);
            $table->unique(['room_id', 'audience']);
        });

        Schema::create('room_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->string('audience', 48);
            $table->unsignedBigInteger('sequence');
            $table->string('event_type', 64);
            $table->string('entity_id', 80)->nullable();
            $table->unsignedInteger('entity_revision')->nullable();
            $table->uuid('command_id');
            $table->json('payload');
            $table->timestampTz('occurred_at');
            $table->timestampTz('queued_at')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestampsTz();
            $table->unique(['room_id', 'audience', 'sequence']);
            $table->index(['published_at', 'queued_at']);
        });

        Schema::create('room_command_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('operation', 48);
            $table->string('idempotency_key', 128);
            $table->char('request_hash', 64);
            $table->uuid('command_id');
            $table->json('response');
            $table->timestampsTz();
            $table->unique(['room_id', 'user_id', 'operation', 'idempotency_key'], 'room_command_receipts_unique');
        });

        Schema::create('room_macros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('scope', 24);
            $table->string('scope_key', 64);
            $table->string('name', 40);
            $table->string('name_key', 80);
            $table->text('body');
            $table->unsignedInteger('revision')->default(1);
            $table->timestampsTz();
            $table->unique(['scope', 'scope_key', 'name_key']);
        });

        Schema::create('dice_rolls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('command_id');
            $table->unsignedSmallInteger('roll_index')->default(0);
            $table->string('expression', 120);
            $table->json('components');
            $table->integer('total');
            $table->timestampsTz();
            $table->unique(['command_id', 'roll_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dice_rolls');
        Schema::dropIfExists('room_macros');
        Schema::dropIfExists('room_command_receipts');
        Schema::dropIfExists('room_events');
        Schema::dropIfExists('room_event_sequences');

        Schema::table('room_messages', function (Blueprint $table) {
            $table->dropIndex(['room_id', 'recipient_user_id', 'id']);
            $table->dropConstrainedForeignId('recipient_user_id');
            $table->dropColumn(['message_type', 'asset_ids']);
        });

        Schema::table('room_map_objects', function (Blueprint $table) {
            $table->dropColumn('revision');
        });

        Schema::table('room_map_layers', function (Blueprint $table) {
            $table->dropColumn('revision');
        });

        Schema::table('room_maps', function (Blueprint $table) {
            $table->dropColumn('revision');
        });

        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn('revision');
        });

        Schema::table('rooms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('active_map_id');
        });
    }
};
