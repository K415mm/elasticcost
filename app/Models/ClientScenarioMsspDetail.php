<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientScenarioMsspDetail extends Model
{
    protected $table = 'client_scenario_mssp_details';

    protected $fillable = [
        'client_id',
        'scenario_id',
        'one_time_setup_cost',
        'monthly_maintenance_cost',
        'ram_monthly_cost_per_gb',
        'nvme_ssd_monthly_cost_per_gb',
        'sata_ssd_monthly_cost_per_gb',
        'local_ssd_monthly_cost_per_gb',
        'is_license_shared',
        'license_share_percentage',
        'assurance_benefit_percentage',
        'marketing_benefit_percentage',
        'soc_manager_benefit_percentage',
        'ceo_benefit_percentage',
        'fixed_profit_percentage',
        'ai_analysis'
    ];

    protected $casts = [
        'one_time_setup_cost' => 'decimal:2',
        'monthly_maintenance_cost' => 'decimal:2',
        'ram_monthly_cost_per_gb' => 'decimal:4',
        'nvme_ssd_monthly_cost_per_gb' => 'decimal:4',
        'sata_ssd_monthly_cost_per_gb' => 'decimal:4',
        'local_ssd_monthly_cost_per_gb' => 'decimal:4',
        'is_license_shared' => 'boolean',
        'license_share_percentage' => 'decimal:2',
        'assurance_benefit_percentage' => 'decimal:2',
        'marketing_benefit_percentage' => 'decimal:2',
        'soc_manager_benefit_percentage' => 'decimal:2',
        'ceo_benefit_percentage' => 'decimal:2',
        'fixed_profit_percentage' => 'decimal:2',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(Scenario::class);
    }

    public function analystAllocations(): HasMany
    {
        return $this->hasMany(ClientScenarioAnalystAllocation::class, 'mssp_details_id');
    }
}
