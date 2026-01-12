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
    ];

    public static function isActive(): bool{
        $schedule = self::latest('id')->first();

        if(!$schedule){
            return false;
        }

        return match ($schedule->type) {
            'disabled' => false,
            'always' => true,
            'range', 'week' => now()->between($schedule->start_date, $schedule->end_date),
            default => false,
        };
    }
}
