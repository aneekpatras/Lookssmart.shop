<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_working_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday'); // 0 (Sunday) - 6 (Saturday)
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();

            $table->index(['staff_id', 'weekday']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_working_hours');
    }
};
