<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (empty($user->role)) {
                $user->role = 'user';
            }
            if (empty($user->status)) {
                $user->status = 'approved';
            }
        });
    }

    public function attendanceCredentials()
    {
        return $this->hasMany(AttendanceCredential::class);
    }

    public function activeAttendanceCredential()
    {
        return $this->hasOne(AttendanceCredential::class)->where('is_active', true)->latestOfMany();
    }

    public function hasActiveAttendanceCredential(): bool
    {
        return $this->attendanceCredentials()->where('is_active', true)->exists();
    }

    public function attendanceRecords()
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'manager', 'executive', 'super_admin'], true);
    }

    public function isExecutive(): bool
    {
        return in_array($this->role, ['executive', 'super_admin'], true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isManager(): bool
    {
        return $this->role === 'manager';
    }

    public function isFinance(): bool
    {
        return $this->role === 'finance';
    }

    public function isFieldStaff(): bool
    {
        return $this->role === 'field_staff';
    }

    public function isUser(): bool
    {
        return $this->role === 'user';
    }

    public function hasRole(array|string $roles): bool
    {
        $roles = is_array($roles) ? $roles : func_get_args();

        if (in_array($this->role, ['executive', 'super_admin'], true) && in_array('admin', $roles, true)) {
            return true;
        }

        return in_array($this->role, $roles, true);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
}
