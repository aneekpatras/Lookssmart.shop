<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 20);
            $table->string('recipient');
            $table->nullableMorphs('notifiable');
            $table->enum('status', ['pending', 'sent', 'failed']);
            $table->string('provider_ref')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('channel');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
