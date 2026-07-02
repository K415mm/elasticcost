<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HarnessSession extends Model
{
    protected $table = 'harness_sessions';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    /**
     * Get granular tool calls and LLM requests details for this session.
     */
    public function details(): HasMany
    {
        return $this->hasMany(HarnessDetail::class, 'session_id', 'id');
    }
}
