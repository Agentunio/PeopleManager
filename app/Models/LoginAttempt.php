<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginAttempt extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'ip_user',
        'success',
    ];

    protected function casts(): array
    {
        return [
            'success' => 'boolean',
            'data' => 'datetime',
        ];
    }

    public static function isBlocked(string $ip): bool
    {
        $count = self::where('ip_user', $ip)
            ->where('success', false)
            ->where('data', '>', now()->subMinutes(30))
            ->count();

        return $count >= 5;
    }

    public static function record(string $ip, bool $success): self
    {
        return self::create([
            'ip_user' => $ip,
            'success' => $success,
        ]);
    }
}
