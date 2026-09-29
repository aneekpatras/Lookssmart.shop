<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_imports', function (Blueprint $table) {
            $table->id();
            $table->string('original_filename');
            $table->string('file_path');
            $table->string('status', 20)->default('queued'); // queued, processing, previewed, committing, committed, failed
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('create_count')->default(0);
            $table->unsignedInteger('update_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->text('failure_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('previewed_at')->nullable();
            $table->timestamp('committed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_imports');
    }
};
