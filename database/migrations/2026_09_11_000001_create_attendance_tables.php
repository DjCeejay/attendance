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
        Schema::create('attendance_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('credential_id', 512)->unique();
            $table->text('public_key');
            $table->string('attestation_format')->default('none');
            $table->unsignedInteger('sign_count')->default(0);
            $table->string('device_name')->nullable();
            $table->text('user_agent')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('registered_at')->useCurrent();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->foreignId('deactivated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });

        Schema::create('attendance_networks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('ip_range');
            $table->boolean('enabled')->default(true);
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });

        Schema::create('attendance_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->date('attendance_date');
            $table->dateTime('check_in_at')->nullable();
            $table->dateTime('check_out_at')->nullable();
            $table->string('status')->default('present');
            $table->string('check_in_method')->nullable();
            $table->string('check_out_method')->nullable();
            $table->boolean('check_in_network_verified')->default(false);
            $table->boolean('check_out_network_verified')->default(false);
            $table->string('check_in_ip')->nullable();
            $table->string('check_out_ip')->nullable();
            $table->foreignId('check_in_credential_id')->nullable()->constrained('attendance_credentials')->onDelete('set null');
            $table->foreignId('check_out_credential_id')->nullable()->constrained('attendance_credentials')->onDelete('set null');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'attendance_date']);
        });

        Schema::create('attendance_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event_type');
            $table->foreignId('actor_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('affected_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('attendance_record_id')->nullable()->constrained('attendance_records')->onDelete('set null');
            $table->json('original_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('reason')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_audit_logs');
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('attendance_settings');
        Schema::dropIfExists('attendance_networks');
        Schema::dropIfExists('attendance_credentials');
    }
};
