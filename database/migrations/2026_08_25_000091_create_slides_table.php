<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('slider_id')->constrained()->cascadeOnDelete();
            $table->string('heading')->nullable();
            $table->string('subheading')->nullable();
            $table->string('image_path');
            $table->string('mobile_image_path')->nullable();
            $table->string('cta_text', 100)->nullable();
            $table->string('cta_url')->nullable();
            $table->string('text_position', 20)->default('center');
            $table->string('animation', 50)->default('fade');
            $table->decimal('overlay_opacity', 3, 2)->default(0.3);
            $table->integer('sort')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('slider_id');
            $table->index('sort');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slides');
    }
};
