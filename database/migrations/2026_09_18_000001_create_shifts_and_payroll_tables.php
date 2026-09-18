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
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g. Morning Shift, Afternoon Shift, Shift 1
            $table->time('resumption_time'); // e.g. 07:00:00
            $table->time('closing_time')->nullable(); // e.g. 15:00:00
            $table->string('department')->default('acf'); // 'acf', 'artsci', or 'all'
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('staff_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->onDelete('cascade');
            $table->decimal('base_salary', 12, 2)->default(0.00);
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->onDelete('set null');
            $table->time('custom_resumption_time')->nullable(); // For ARTSCI or individual overrides
            $table->json('off_days')->nullable(); // e.g. [0] for Sunday, [0, 6] for Sat & Sun
            $table->unsignedInteger('grace_period_minutes')->default(15);
            $table->timestamps();
        });

        Schema::create('salary_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('attendance_record_id')->nullable()->constrained('attendance_records')->onDelete('set null');
            $table->string('pay_period', 7); // e.g. '2026-09'
            $table->decimal('amount', 10, 2)->default(500.00);
            $table->string('deduction_type')->default('lateness'); // 'lateness', 'manual_penalty'
            $table->text('reason')->nullable();
            $table->string('status')->default('active'); // 'active', 'waived'
            $table->foreignId('waived_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('waived_at')->nullable();
            $table->text('waiver_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('payroll_archives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('pay_period', 7); // e.g. '2026-09'
            $table->decimal('base_salary', 12, 2)->default(0.00);
            $table->decimal('total_deductions', 12, 2)->default(0.00);
            $table->decimal('net_salary', 12, 2)->default(0.00);
            $table->unsignedInteger('late_count')->default(0);
            $table->foreignId('reset_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reset_at')->useCurrent();
            $table->timestamps();

            $table->unique(['user_id', 'pay_period']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_archives');
        Schema::dropIfExists('salary_deductions');
        Schema::dropIfExists('staff_profiles');
        Schema::dropIfExists('shifts');
    }
};
