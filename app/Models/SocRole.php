<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocRole extends Model
{
    protected $fillable = ['name', 'default_monthly_salary', 'description'];

    protected $casts = [
        'default_monthly_salary' => 'decimal:2',
    ];

    public function analystAllocations(): HasMany
    {
        return $this->hasMany(ClientScenarioAnalystAllocation::class, 'soc_role_id');
    }
}
