<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceAuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_type',
        'actor_id',
        'affected_user_id',
        'attendance_record_id',
        'original_values',
        'new_values',
        'reason',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'original_values' => 'array',
            'new_values' => 'array',
        ];
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function affectedUser()
    {
        return $this->belongsTo(User::class, 'affected_user_id');
    }

    public function attendanceRecord()
    {
        return $this->belongsTo(AttendanceRecord::class, 'attendance_record_id');
    }

    public static function logEvent(
        string $eventType,
        ?User $actor = null,
        ?User $affectedUser = null,
        ?AttendanceRecord $record = null,
        ?array $originalValues = null,
        ?array $newValues = null,
        ?string $reason = null,
        ?string $ipAddress = null
    ): self {
        return static::create([
            'event_type' => $eventType,
            'actor_id' => $actor?->id,
            'affected_user_id' => $affectedUser?->id,
            'attendance_record_id' => $record?->id,
            'original_values' => $originalValues,
            'new_values' => $newValues,
            'reason' => $reason,
            'ip_address' => $ipAddress ?? request()->ip(),
        ]);
    }
}
