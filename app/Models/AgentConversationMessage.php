<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AgentConversationMessage extends Model
{
    protected $table = 'agent_conversation_messages';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'conversation_id',
        'user_id',
        'agent',
        'role',
        'content',
        'attachments',
        'tool_calls',
        'tool_results',
        'usage',
        'meta',
    ];

    protected $casts = [
        'attachments' => 'json',
        'tool_calls' => 'json',
        'tool_results' => 'json',
        'usage' => 'json',
        'meta' => 'json',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
            if (is_null($model->attachments)) {
                $model->attachments = [];
            }
            if (is_null($model->tool_calls)) {
                $model->tool_calls = [];
            }
            if (is_null($model->tool_results)) {
                $model->tool_results = [];
            }
            if (is_null($model->usage)) {
                $model->usage = [];
            }
            if (is_null($model->meta)) {
                $model->meta = [];
            }
        });
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AgentConversation::class, 'conversation_id', 'id');
    }
}
