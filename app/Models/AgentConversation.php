<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AgentConversation extends Model
{
    protected $table = 'agent_conversations';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_id',
        'title',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AgentConversationMessage::class, 'conversation_id', 'id')->orderBy('created_at', 'asc');
    }
}
