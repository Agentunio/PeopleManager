<?php

namespace App\Models;

use App\Notifications\QueuedResetPassword;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

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
        if (! $this->activation_expires_at) {
            return true;
        }

        return Carbon::parse($this->activation_expires_at)->isPast();
    }

    /**
     * Queued so the response time never reveals whether the account exists
     * and SMTP stays out of the request cycle.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new QueuedResetPassword($token));
    }

    protected $hidden = [
        'password',
        'activation_token',
        'remember_token',
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
