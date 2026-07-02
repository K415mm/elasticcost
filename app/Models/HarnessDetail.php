<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HarnessDetail extends Model
{
    protected $table = 'harness_details';

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'response' => 'array',
    ];

    /**
     * Get the harness session that owns this log detail.
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(HarnessSession::class, 'session_id', 'id');
    }
}
