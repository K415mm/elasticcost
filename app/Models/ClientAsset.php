<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientAsset extends Model
{
    protected $fillable = [
        'client_id',
        'asset_type_id',
        'device_count',
        'custom_avg_event_size_bytes',
        'custom_min_eps',
        'custom_avg_eps',
        'custom_max_eps',
        'custom_max_monthly_gb',
        'runs_siem_agent',
        'runs_mdr_agent',
        'runs_edr_agent',
    ];

    protected $casts = [
        'device_count' => 'integer',
        'custom_avg_event_size_bytes' => 'integer',
        'custom_min_eps' => 'float',
        'custom_avg_eps' => 'float',
        'custom_max_eps' => 'float',
        'custom_max_monthly_gb' => 'float',
        'runs_siem_agent' => 'boolean',
        'runs_mdr_agent' => 'boolean',
        'runs_edr_agent' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($clientAsset) {
            if ($clientAsset->assetType) {
                if ($clientAsset->runs_siem_agent === null) {
                    $clientAsset->runs_siem_agent = (bool) $clientAsset->assetType->runs_siem_agent;
                }
                if ($clientAsset->runs_mdr_agent === null) {
                    $clientAsset->runs_mdr_agent = (bool) $clientAsset->assetType->runs_mdr_agent;
                }
                if ($clientAsset->runs_edr_agent === null) {
                    $clientAsset->runs_edr_agent = (bool) $clientAsset->assetType->runs_edr_agent;
                }
            }
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function assetType(): BelongsTo
    {
        return $this->belongsTo(AssetType::class);
    }

    /**
     * Resolves the active event size in bytes.
     */
    public function getActiveEventSizeAttribute(): int
    {
        return $this->custom_avg_event_size_bytes ?? $this->assetType->avg_event_size_bytes;
    }

    /**
     * Resolves the active calibration mode.
     */
    public function getActiveCalibrationModeAttribute(): string
    {
        return $this->assetType->calibration_mode;
    }
}
