<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    protected $table = 'schedules';

    public $timestamps = true;

    protected $fillable = [
        'type',
        'start_date',
        'end_date',
        'id',
    ];

    public static function getCurrent(): ?self
    {
        return self::first();
    }

    public function isActive(): bool
    {
        return match ($this->type) {
            'disabled' => false,
            'always' => true,
            'range', 'week' => now()->between($this->start_date, $this->end_date),
            default => false,
        };
    }
}
