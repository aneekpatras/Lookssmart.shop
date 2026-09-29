<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 12 sub-step 2: individual cash-in/cash-out entries against a register shift, with a reason
 * per entry — `cash_register_shifts.notes` (Phase 2) is a single free-text field, not a real log, so
 * it can't support "log float additions or cash drops with staff notes" as a real audit trail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_register_shift_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('users')->restrictOnDelete();
            $table->enum('type', ['deposit', 'withdrawal']);
            $table->decimal('amount', 12, 2);
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index('cash_register_shift_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
    }
};
