<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
    protected $table = 'settings';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'price',
    ];

    public function shifts(): HasMany
    {
        return $this->hasMany(WorkerShift::class);
    }
}
