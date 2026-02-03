<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkerShift extends Model
{
    protected $table = 'worker_shifts';

    public $timestamps = true;

    protected $fillable = [
        'worker_id',
        'day',
        'shift_type',
        'package_id',
        'minutes',
    ];

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }
}
