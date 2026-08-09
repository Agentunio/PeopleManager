<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $first_name
 * @property string $last_name
 * @property-read bool $is_available
 */
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

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function hasAccount(): bool
    {
        return $this->user !== null;
    }

    public function availabilities(): HasMany
    {
        return $this->hasMany(WorkerAvailability::class);
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(WorkerShift::class);
    }
}
