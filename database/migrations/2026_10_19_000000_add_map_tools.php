<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('room_maps', function (Blueprint $table) {
            $table->boolean('fog_enabled')->default(false);
            $table->json('fog_revealed')->default('[]');
            $table->decimal('distance_scale', 10, 3)->default(1.5);
            $table->string('distance_unit', 20)->default('m');
        });
        Schema::create('room_map_drawings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_map_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('kind', 12);
            $table->string('color', 7);
            $table->unsignedTinyInteger('stroke_width');
            $table->json('points');
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('room_map_drawings');
        Schema::table('room_maps', fn (Blueprint $table) => $table->dropColumn(['fog_enabled', 'fog_revealed', 'distance_scale', 'distance_unit']));
    }
};
