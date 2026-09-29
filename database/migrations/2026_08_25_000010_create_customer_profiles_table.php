<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->date('dob')->nullable();
            $table->string('gender', 20)->nullable();
            $table->json('preferences')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('total_spent', 12, 2)->default(0);
            $table->unsignedInteger('visits')->default(0);
            $table->json('tags')->nullable();
            $table->unsignedInteger('loyalty_points')->default(0);
            $table->unsignedInteger('no_show_count')->default(0);
            $table->boolean('is_blacklisted')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_profiles');
    }
};
