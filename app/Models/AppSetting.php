<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    public const KEY_WORKER_SELF_HOURS = 'worker_self_hours_enabled';

    protected $table = 'app_settings';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public static function getBool(string $key): bool
    {
        return (bool) (int) (static::find($key)?->value ?? '0');
    }

    public static function set(string $key, mixed $value): void
    {
        $stringValue = is_bool($value) ? ($value ? '1' : '0') : (string) $value;

        static::updateOrCreate(['key' => $key], ['value' => $stringValue]);
    }
}
