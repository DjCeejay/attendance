<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'resumption_time',
        'closing_time',
        'department',
        'description',
    ];

    public function staffProfiles()
    {
        return $this->hasMany(StaffProfile::class);
    }

    public function getFormattedResumptionAttribute(): string
    {
        return \Carbon\Carbon::parse($this->resumption_time)->format('g:i A');
    }

    public function getFormattedClosingAttribute(): ?string
    {
        return $this->closing_time ? \Carbon\Carbon::parse($this->closing_time)->format('g:i A') : null;
    }
}
