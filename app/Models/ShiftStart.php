<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShiftStart extends Model
{
    protected $fillable = [
        'day',
        'shift_type',
        'start_time',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'integer',
        ];
    }
}
