<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    protected $table = 'schedules';

    public $timestamps = true;

    protected $fillable = [
        'type',
        'start_date',
        'end_date',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'datetime',
            'end_date' => 'datetime',
        ];
    }

    public static function getCurrent(): ?self
    {
        return self::latest('id')->first();
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

    public function isDateInSchedule(Carbon $date): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        if ($this->end_date && $date->gt($this->end_date)) {
            return false;
        }

        return true;
    }
}
