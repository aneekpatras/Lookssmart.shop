<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gallery_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gallery_id')->constrained()->cascadeOnDelete();
            $table->string('image_path');
            $table->string('caption')->nullable();
            $table->boolean('is_before_after')->default(false);
            $table->string('pair_image_path')->nullable();
            $table->integer('sort')->default(0);
            $table->timestamps();

            $table->index('gallery_id');
            $table->index('sort');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_images');
    }
};
