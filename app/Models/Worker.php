<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Worker extends Model
{

    public $timestamps = false;

    protected $fillable = [
        'first_name',
        'last_name',
        'phone',
        'address',
        'date_of_birth',
        'is_student',
        'is_employed',
        'contract_from',
        'contract_to',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'contract_from' => 'date',
            'contract_to' => 'date',
            'is_student' => 'boolean',
            'is_employed' => 'boolean',
        ];
    }
}
