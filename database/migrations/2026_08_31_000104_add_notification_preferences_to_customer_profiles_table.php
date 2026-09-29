<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_profiles', function (Blueprint $table) {
            $table->boolean('marketing_opt_in')->default(false)->after('preferences');
            $table->boolean('email_opt_out')->default(false)->after('marketing_opt_in');
            $table->boolean('sms_opt_out')->default(false)->after('email_opt_out');
            $table->boolean('whatsapp_opt_out')->default(false)->after('sms_opt_out');
            $table->timestamp('consented_at')->nullable()->after('whatsapp_opt_out');
        });
    }

    public function down(): void
    {
        Schema::table('customer_profiles', function (Blueprint $table) {
            $table->dropColumn(['marketing_opt_in', 'email_opt_out', 'sms_opt_out', 'whatsapp_opt_out', 'consented_at']);
        });
    }
};
