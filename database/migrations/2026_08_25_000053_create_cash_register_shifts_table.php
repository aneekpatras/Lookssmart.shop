<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_register_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('users')->restrictOnDelete(); // who opened it (any user)
            $table->decimal('opening_float', 12, 2);
            $table->decimal('closing_float', 12, 2)->nullable();
            $table->decimal('expected_total', 12, 2)->nullable();
            $table->decimal('counted_total', 12, 2)->nullable();
            $table->decimal('variance', 12, 2)->nullable();
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->dateTime('opened_at');
            $table->dateTime('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('staff_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_register_shifts');
    }
};
