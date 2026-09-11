<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'attendance_date',
        'check_in_at',
        'check_out_at',
        'status',
        'check_in_method',
        'check_out_method',
        'check_in_network_verified',
        'check_out_network_verified',
        'check_in_ip',
        'check_out_ip',
        'check_in_credential_id',
        'check_out_credential_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
            'check_in_network_verified' => 'boolean',
            'check_out_network_verified' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function checkInCredential()
    {
        return $this->belongsTo(AttendanceCredential::class, 'check_in_credential_id');
    }

    public function checkOutCredential()
    {
        return $this->belongsTo(AttendanceCredential::class, 'check_out_credential_id');
    }

    public function isCheckedIn(): bool
    {
        return !is_null($this->check_in_at) && is_null($this->check_out_at);
    }

    public function isCheckedOut(): bool
    {
        return !is_null($this->check_out_at);
    }

    public function isLate(): bool
    {
        return $this->status === 'late';
    }
}
