<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The original `UNIQUE(staff_id, starts_at)` guard (Phase 2) blocks double-BOOKING correctly, but
 * blocks forever, regardless of status — once a booking at a given slot is cancelled, the physical
 * row still occupies that (staff_id, starts_at) pair, so no one could ever book that exact slot again.
 * Found and fixed while building Phase 7 sub-step 3's cancel flow, since cancelling is meant to free
 * the slot back up (Brief §4's whole point of a "Cancellation window"). Fixed with a generated column
 * that's NULL for cancelled/no_show bookings — a unique index on it allows unlimited cancelled rows
 * for the same slot (SQL unique indexes never consider NULLs equal to each other) while still blocking
 * two simultaneously-active bookings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // MariaDB/MySQL won't drop `unique(staff_id, starts_at)` while it's the only index
            // covering the `staff_id` foreign key — add a plain index first so the FK stays supported.
            $table->index('staff_id', 'bookings_staff_id_index');
            $table->dropUnique(['staff_id', 'starts_at']);
        });

        if (DB::getDriverName() === 'sqlite') {
            // SQLite (used by the test suite) has no generated-column syntax matching MySQL/MariaDB's —
            // a partial unique index via a WHERE clause is SQLite's native equivalent and achieves the
            // exact same "cancelled/no_show rows don't collide" semantics.
            DB::statement(
                'CREATE UNIQUE INDEX bookings_active_slot_unique ON bookings (staff_id, starts_at) ' .
                "WHERE status NOT IN ('cancelled', 'no_show') AND deleted_at IS NULL",
            );

            return;
        }

        DB::statement(
            'ALTER TABLE bookings ADD COLUMN active_slot_key VARCHAR(191) GENERATED ALWAYS AS ' .
            "(CASE WHEN status NOT IN ('cancelled', 'no_show') AND deleted_at IS NULL " .
            "THEN CONCAT(staff_id, '|', starts_at) ELSE NULL END) STORED",
        );
        DB::statement('ALTER TABLE bookings ADD UNIQUE INDEX bookings_active_slot_unique (active_slot_key)');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS bookings_active_slot_unique');
        } else {
            DB::statement('ALTER TABLE bookings DROP INDEX bookings_active_slot_unique');
            DB::statement('ALTER TABLE bookings DROP COLUMN active_slot_key');
        }

        Schema::table('bookings', function (Blueprint $table) {
            $table->unique(['staff_id', 'starts_at']);
            $table->dropIndex('bookings_staff_id_index');
        });
    }
};
