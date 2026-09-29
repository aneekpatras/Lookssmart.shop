<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('guest_name')->nullable();
            $table->text('guest_email')->nullable(); // encrypted cast — text for ciphertext length
            $table->text('guest_phone')->nullable(); // encrypted cast — text for ciphertext length
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->dateTime('starts_at'); // always UTC
            $table->dateTime('ends_at'); // always UTC
            $table->enum('status', ['pending', 'confirmed', 'checked_in', 'completed', 'cancelled', 'no_show'])
                ->default('pending');
            $table->string('source', 50)->default('website'); // website, admin, phone, walk-in
            $table->decimal('total', 12, 2);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->string('calendar_event_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['staff_id', 'starts_at']); // DB-level double-booking guard (Brief §4)
            $table->index('customer_id');
            $table->index('status');
            $table->index('starts_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
