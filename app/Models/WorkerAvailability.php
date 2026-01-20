<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkerAvailability extends Model
{
    protected $table = 'worker_availability';

    public $timestamps = true;

    protected $fillable = [
        'worker_id',
        'day',
        'morning_shift',
        'afternoon_shift',
    ];

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }
}
