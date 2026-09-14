<?php

namespace App\Console\Commands;

use App\Models\AttendanceAuditLog;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSetting;
use Carbon\Carbon;
use Illuminate\Console\Command;

class FixWatTimestampsCommand extends Command
{
    protected $signature = 'attendance:fix-wat {--date= : Specific date YYYY-MM-DD to fix (defaults to today)} {--hours=1 : Hours to add (+1 for UTC to WAT)}';

    protected $description = "Adjust check-in timestamps recorded in UTC to WAT (+1 hour) and re-evaluate late status for the specified date.";

    public function handle(): int
    {
        $tz = AttendanceSetting::get('timezone', config('app.timezone', 'Africa/Lagos'));
        $dateStr = $this->option('date') ?: Carbon::today($tz)->toDateString();
        $hoursToAdd = (int) $this->option('hours');

        $expectedTimeStr = AttendanceSetting::get('expected_arrival_time', '09:00');
        $lateThreshold = (int) AttendanceSetting::get('late_threshold_minutes', 15);

        $records = AttendanceRecord::whereDate('attendance_date', $dateStr)->get();

        if ($records->isEmpty()) {
            $this->info("No attendance records found for date {$dateStr}.");
            return 0;
        }

        $this->info("Processing " . $records->count() . " records for {$dateStr} (Adding {$hoursToAdd} hour(s))...");

        $count = 0;
        foreach ($records as $record) {
            if (!$record->check_in_at) {
                continue;
            }

            // Adjust check-in time by adding +1 hour
            $newCheckIn = Carbon::parse($record->check_in_at)->addHours($hoursToAdd);

            // Re-evaluate late status in local WAT timezone
            $expectedArrival = Carbon::createFromFormat('Y-m-d H:i', $dateStr . ' ' . $expectedTimeStr, $tz);
            $lateDeadline = (clone $expectedArrival)->addMinutes($lateThreshold);

            $newStatus = $newCheckIn->greaterThan($lateDeadline) ? 'late' : 'present';

            $oldStatus = $record->status;
            $oldCheckInStr = $record->check_in_at->format('g:i A');

            $record->check_in_at = $newCheckIn;
            $record->status = $newStatus;

            // Also adjust check_out_at if set
            if ($record->check_out_at) {
                $record->check_out_at = Carbon::parse($record->check_out_at)->addHours($hoursToAdd);
            }

            $record->save();
            $count++;

            AttendanceAuditLog::logEvent(
                eventType: 'manual_correction',
                actor: null,
                affectedUser: $record->user,
                record: $record,
                newValues: [
                    'check_in_at' => $newCheckIn->toIso8601String(),
                    'status' => $newStatus,
                ],
                reason: "CLI WAT Shift (+{$hoursToAdd} hr): Old CheckIn {$oldCheckInStr} ({$oldStatus}) -> New CheckIn {$newCheckIn->format('g:i A')} ({$newStatus})"
            );

            $this->line(" - User: {$record->user->name} | Old: {$oldCheckInStr} ({$oldStatus}) -> New: {$newCheckIn->format('g:i A')} ({$newStatus})");
        }

        $this->info("Successfully updated {$count} record(s).");
        return 0;
    }
}
