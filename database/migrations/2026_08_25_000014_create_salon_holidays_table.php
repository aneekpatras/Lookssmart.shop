<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salon_holidays', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('starts_at');
            $table->date('ends_at');
            $table->boolean('is_recurring_yearly')->default(false);
            $table->timestamps();

            $table->index(['starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salon_holidays');
    }
};
