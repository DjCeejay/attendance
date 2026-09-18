<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalaryDeduction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'attendance_record_id',
        'pay_period',
        'amount',
        'deduction_type',
        'reason',
        'status',
        'waived_by',
        'waived_at',
        'waiver_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'waived_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function attendanceRecord()
    {
        return $this->belongsTo(AttendanceRecord::class);
    }

    public function waivedBy()
    {
        return $this->belongsTo(User::class, 'waived_by');
    }

    public function isWaived(): bool
    {
        return $this->status === 'waived';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
