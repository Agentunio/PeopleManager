<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
        'status',
        'substituted_for_shift_id',
    ];

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function substitutedForShift(): BelongsTo
    {
        return $this->belongsTo(self::class, 'substituted_for_shift_id');
    }

    public function substitute(): HasOne
    {
        return $this->hasOne(self::class, 'substituted_for_shift_id');
    }
}
