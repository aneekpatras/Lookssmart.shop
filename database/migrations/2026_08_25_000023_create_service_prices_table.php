<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('price_list', 50)->default('standard'); // standard, weekend, seasonal, ...
            $table->decimal('price', 12, 2);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->index(['service_id', 'price_list', 'effective_from', 'effective_to'], 'service_prices_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_prices');
    }
};
