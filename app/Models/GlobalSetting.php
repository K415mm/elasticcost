<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GlobalSetting extends Model
{
    protected $table = 'global_settings';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value', 'description'];

    /**
     * Helper to retrieve a global setting's value.
     */
    public static function getValue(string $key, mixed $default = null): mixed
    {
        $setting = self::find($key);

        return $setting ? $setting->value : $default;
    }
}
