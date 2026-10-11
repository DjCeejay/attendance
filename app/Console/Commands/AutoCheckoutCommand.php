<?php

namespace App\Console\Commands;

use App\Models\AttendanceAuditLog;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSetting;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AutoCheckoutCommand extends Command
{
    protected $signature = 'attendance:auto-checkout {--force : Force execute regardless of setting}';
    protected $description = 'Automatically check out staff who forgot to check out after the configured auto-checkout time.';

    public function handle(): int
    {
        if (!$this->option('force') && !AttendanceSetting::get('auto_checkout_enabled', true)) {
            $this->info("Auto check-out is currently disabled in Attendance Settings.");
            return Command::SUCCESS;
        }

        $tz       = AttendanceSetting::get('timezone', config('app.timezone', 'Africa/Lagos'));
        $autoTime = AttendanceSetting::get('auto_checkout_time', '17:00');
        $nowTz    = Carbon::now($tz);

        $unclosedRecords = AttendanceRecord::whereNotNull('check_in_at')
            ->whereNull('check_out_at')
            ->get();

        if ($unclosedRecords->isEmpty()) {
            $this->info("No unclosed attendance records found.");
            return Command::SUCCESS;
        }

        $processed = 0;
        foreach ($unclosedRecords as $rec) {
            $recDate = $rec->attendance_date;
            $autoCheckoutDateTime = Carbon::createFromFormat(
                'Y-m-d H:i',
                $recDate->toDateString() . ' ' . $autoTime,
                $tz
            );

            if ($this->option('force') || $nowTz->greaterThanOrEqualTo($autoCheckoutDateTime)) {
                $rec->update([
                    'check_out_at' => $autoCheckoutDateTime,
                    'notes'        => trim(($rec->notes ? $rec->notes . ' | ' : '') . 'System Auto Check-Out (Forgotten Check-Out)'),
                ]);

                AttendanceAuditLog::logEvent(
                    eventType:    'auto_checkout',
                    actor:        null,
                    affectedUser: $rec->user,
                    record:       $rec,
                    reason:       'Scheduled Auto Check-Out triggered for forgotten check-out'
                );

                $this->line("Auto checked out: {$rec->user->name} on {$recDate->toDateString()} at {$autoCheckoutDateTime->format('g:i A')}");
                $processed++;
            }
        }

        $this->info("Completed auto check-out. Processed {$processed} record(s).");
        return Command::SUCCESS;
    }
}
