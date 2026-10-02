<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('attendance_networks', function (Blueprint $table) {
            $table->string('ddns_hostname')->nullable()->after('ip_range');
            $table->boolean('ddns_enabled')->default(false)->after('ddns_hostname');
            $table->string('last_resolved_ip', 45)->nullable()->after('ddns_enabled');
            $table->timestamp('last_resolved_at')->nullable()->after('last_resolved_ip');
            $table->string('resolution_status', 50)->default('not_configured')->after('last_resolved_at');
            $table->text('resolution_error')->nullable()->after('resolution_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_networks', function (Blueprint $table) {
            $table->dropColumn([
                'ddns_hostname',
                'ddns_enabled',
                'last_resolved_ip',
                'last_resolved_at',
                'resolution_status',
                'resolution_error',
            ]);
        });
    }
};
