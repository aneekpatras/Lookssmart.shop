<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deal_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code_used', 50)->nullable();
            $table->decimal('discount_amount', 12, 2);
            $table->dateTime('redeemed_at');

            $table->index(['deal_id', 'customer_id']);
            $table->index('booking_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deal_redemptions');
    }
};
