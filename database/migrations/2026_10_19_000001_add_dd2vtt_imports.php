<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_maps', function (Blueprint $table) {
            $table->jsonb('import_data')->nullable();
        });
        Schema::create('map_import_previews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 100);
            $table->string('image_path');
            $table->string('image_hash', 64);
            $table->jsonb('validated');
            $table->timestamp('expires_at')->index();
            $table->foreignId('map_id')->nullable()->constrained('room_maps')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('map_import_previews');
        Schema::table('room_maps', fn (Blueprint $t) => $t->dropColumn('import_data'));
    }
};
