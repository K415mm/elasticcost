<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientScenarioAnalystAllocation extends Model
{
    protected $table = 'client_scenario_analyst_allocations';

    protected $fillable = [
        'mssp_details_id',
        'soc_role_id',
        'allocation_percentage',
        'custom_monthly_salary',
        'staff_count',
    ];

    protected $casts = [
        'allocation_percentage' => 'decimal:2',
        'custom_monthly_salary' => 'decimal:2',
        'staff_count' => 'integer',
    ];

    public function msspDetail(): BelongsTo
    {
        return $this->belongsTo(ClientScenarioMsspDetail::class, 'mssp_details_id');
    }

    public function socRole(): BelongsTo
    {
        return $this->belongsTo(SocRole::class, 'soc_role_id');
    }
}
