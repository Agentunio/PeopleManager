<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{

    public $timestamps = true;

    protected $attributes = [
        'is_active' => true,
    ];

    protected $fillable = [
        'username',
        'password',
        'worker_id',
        'role',
        'email',
        'is_active',
        'activation_token',
        'activation_expires_at',
    ];

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    public function hasExpiredActivation(): bool
    {
        if (!$this->activation_expires_at) {
            return true;
        }

        return Carbon::parse($this->activation_expires_at)->isPast();
    }

    protected $hidden = [
        'password',
        'activation_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'activation_expires_at' => 'datetime',
        ];
    }
}
