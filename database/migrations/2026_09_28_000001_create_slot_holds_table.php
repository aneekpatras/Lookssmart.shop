<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Redis-free replacement for the `booking:hold:*`/`booking:hold:capacity:*` Redis keys
 * `SlotHoldService` used to manage. One row per per-staff hold (`staff_id` set, `seat` null) or per
 * numbered capacity seat (`seat` set, `staff_id` null) — the two unique indexes are the atomicity
 * primitives `SlotHoldService::claim()` locks with `lockForUpdate()`, mirroring what Redis `SET NX`
 * gave for free. `expires_at` is checked inline on every claim attempt rather than relying on a TTL
 * daemon, so an expired row is simply overwritten the next time its slot is claimed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slot_holds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('staff_id')->nullable();
            $table->unsignedInteger('seat')->nullable();
            $table->dateTime('starts_at');
            $table->uuid('token');
            $table->dateTime('expires_at');
            $table->timestamps();

            $table->unique(['staff_id', 'starts_at']);
            $table->unique(['seat', 'starts_at']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slot_holds');
    }
};
