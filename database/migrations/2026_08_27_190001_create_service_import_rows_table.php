<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_import_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('action', 10); // create, update, error
            $table->foreignId('matched_service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->json('data'); // the sanitized, parsed row — never raw formula-injectable strings
            $table->json('errors')->nullable();
            $table->timestamps();

            $table->index(['service_import_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_import_rows');
    }
};
