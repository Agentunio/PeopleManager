<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class PackageShift extends Model
{
    protected $fillable = [
        'day',
        'shift_type',
        'packages_count',
        'package_id',
    ];

    public function packageRate(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'package_id');
    }
}
