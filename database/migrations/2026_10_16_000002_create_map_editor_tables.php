<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_asset_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('room_asset_folders')->nullOnDelete();
            $table->string('name', 80);
            $table->boolean('is_master_only')->default(false);
            $table->timestamps();
            $table->index(['room_id', 'parent_id']);
        });

        Schema::create('room_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('folder_id')->nullable()->constrained('room_asset_folders')->nullOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('kind', 24);
            $table->string('name', 120);
            $table->string('path');
            $table->string('mime_type', 80);
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->boolean('is_master_only')->default(false);
            $table->timestamps();
            $table->index(['room_id', 'kind']);
        });

        Schema::create('room_maps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('background_asset_id')->nullable()->constrained('room_assets')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title', 100);
            $table->unsignedInteger('width')->default(1600);
            $table->unsignedInteger('height')->default(1000);
            $table->unsignedInteger('cell_size')->default(50);
            $table->string('grid_color', 7)->default('#ffffff');
            $table->double('grid_opacity')->default(0.35);
            $table->boolean('grid_enabled')->default(true);
            $table->timestamps();
            $table->unique(['room_id', 'title']);
        });

        Schema::create('room_map_layers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_map_id')->constrained('room_maps')->cascadeOnDelete();
            $table->string('type', 24);
            $table->string('name', 80);
            $table->unsignedSmallInteger('sort_order');
            $table->boolean('visible')->default(true);
            $table->boolean('locked')->default(false);
            $table->boolean('is_master_only')->default(false);
            $table->timestamps();
            $table->unique(['room_map_id', 'type']);
            $table->index(['room_map_id', 'sort_order']);
        });

        Schema::create('room_map_objects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_map_id')->constrained('room_maps')->cascadeOnDelete();
            $table->foreignId('room_map_layer_id')->constrained('room_map_layers')->cascadeOnDelete();
            $table->foreignId('room_asset_id')->constrained('room_assets')->restrictOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 120);
            $table->double('x');
            $table->double('y');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->double('rotation')->default(0);
            $table->integer('z_index')->default(0);
            $table->boolean('visible')->default(true);
            $table->boolean('locked')->default(false);
            $table->timestamps();
            $table->index(['room_map_id', 'room_map_layer_id', 'z_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_map_objects');
        Schema::dropIfExists('room_map_layers');
        Schema::dropIfExists('room_maps');
        Schema::dropIfExists('room_assets');
        Schema::dropIfExists('room_asset_folders');
    }
};
