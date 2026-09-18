<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'base_salary',
        'shift_id',
        'custom_resumption_time',
        'off_days',
        'grace_period_minutes',
    ];

    protected function casts(): array
    {
        return [
            'base_salary' => 'decimal:2',
            'off_days' => 'array',
            'grace_period_minutes' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    /**
     * Get expected resumption time string (H:i format e.g. "08:00")
     */
    public function getExpectedResumptionTime(string $defaultTime = '09:00'): string
    {
        if ($this->custom_resumption_time) {
            return \Carbon\Carbon::parse($this->custom_resumption_time)->format('H:i');
        }

        if ($this->shift && $this->shift->resumption_time) {
            return \Carbon\Carbon::parse($this->shift->resumption_time)->format('H:i');
        }

        return $defaultTime;
    }

    /**
     * Check if a given date (or Carbon instance) is an assigned off-day for this staff.
     * Off days are stored as array of day-of-week integers (0 = Sunday, 1 = Monday ... 6 = Saturday)
     */
    public function isOffDay($date): bool
    {
        if (empty($this->off_days) || !is_array($this->off_days)) {
            return false;
        }

        $carbon = $date instanceof \Carbon\Carbon ? $date : \Carbon\Carbon::parse($date);
        $dayOfWeek = $carbon->dayOfWeek; // 0 for Sunday ... 6 for Saturday

        return in_array($dayOfWeek, $this->off_days, false) || in_array((string)$dayOfWeek, $this->off_days, false);
    }
}
