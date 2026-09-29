<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deals', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->enum('type', ['percent', 'fixed', 'bundle']);
            $table->decimal('value', 12, 2);
            $table->string('code', 50)->nullable()->unique();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('per_user_limit')->nullable();
            $table->decimal('min_amount', 12, 2)->nullable();
            $table->boolean('is_stackable')->default(false);
            $table->boolean('is_auto_apply')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
            $table->index(['starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deals');
    }
};
