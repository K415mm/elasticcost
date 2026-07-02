<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Scenario extends Model
{
    protected $fillable = [
        'name',
        'description',
        'workload_profile',
        'retention_days',
        'hot_days',
        'warm_days',
        'cold_days',
        'frozen_days',
        'hot_replicas',
        'warm_replicas',
        'cold_replicas',
        'frozen_replicas',
        'is_system_default',
    ];

    protected $casts = [
        'retention_days' => 'integer',
        'hot_days' => 'integer',
        'warm_days' => 'integer',
        'cold_days' => 'integer',
        'frozen_days' => 'integer',
        'hot_replicas' => 'integer',
        'warm_replicas' => 'integer',
        'cold_replicas' => 'integer',
        'frozen_replicas' => 'integer',
        'is_system_default' => 'boolean',
    ];
}
