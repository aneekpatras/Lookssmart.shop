<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->decimal('price_snapshot', 12, 2);
            $table->unsignedSmallInteger('duration_snapshot');
            $table->timestamps();

            $table->index('booking_id');
            $table->index('service_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_items');
    }
};
