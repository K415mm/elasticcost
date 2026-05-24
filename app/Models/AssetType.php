<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetType extends Model
{
    protected $fillable = [
        'name',
        'avg_event_size_bytes',
        'calibration_mode',
        'min_eps_default',
        'avg_eps_default',
        'max_eps_default',
        'max_monthly_gb_default',
        'description'
    ];

    protected $casts = [
        'avg_event_size_bytes' => 'integer',
        'min_eps_default' => 'float',
        'avg_eps_default' => 'float',
        'max_eps_default' => 'float',
        'max_monthly_gb_default' => 'float',
    ];

    public function clientAssets(): HasMany
    {
        return $this->hasMany(ClientAsset::class);
    }
}
