<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 11 sub-step 2: the reply thread for a contact message. `messages.replied_at` (Phase 2) only
 * ever recorded *that* a reply happened, not its content — this table stores the actual reply body so
 * "threaded" (per Brief §11 item 2) is real, not just a timestamp.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index('message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_replies');
    }
};
