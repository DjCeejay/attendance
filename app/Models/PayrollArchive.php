<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollArchive extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'pay_period',
        'base_salary',
        'total_deductions',
        'net_salary',
        'late_count',
        'reset_by',
        'reset_at',
    ];

    protected function casts(): array
    {
        return [
            'base_salary' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'net_salary' => 'decimal:2',
            'reset_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function resetBy()
    {
        return $this->belongsTo(User::class, 'reset_by');
    }
}
