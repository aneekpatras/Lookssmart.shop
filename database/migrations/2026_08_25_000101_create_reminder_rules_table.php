<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminder_rules', function (Blueprint $table) {
            $table->id();
            $table->enum('event', ['before_booking', 'after_booking', 'birthday', 'no_show_followup', 'review_request']);
            $table->enum('direction', ['before', 'after'])->default('before');
            $table->unsignedInteger('offset_minutes');
            $table->json('channels'); // e.g. ['email', 'sms', 'whatsapp']
            $table->string('template', 100); // Blade template slug (Phase 8), not DB-driven
            $table->boolean('is_active')->default(true);
            $table->json('target_filters')->nullable();
            $table->timestamps();

            $table->index('event');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_rules');
    }
};
