<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
    protected $table = 'packages';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'price',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function shifts(): HasMany
    {
        return $this->hasMany(WorkerShift::class);
    }

    public function shift_package(): HasMany
    {
        return $this->hasMany(PackageShift::class);
    }

    public static function setAsDefault(?int $id): void
    {
        static::where('is_default', true)->update(['is_default' => false]);

        if ($id) {
            static::where('id', $id)->update(['is_default' => true]);
        }
    }
}
