<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    protected $fillable = [
        'original_name',
        'filename',
        'mime_type',
        'size',
        'status',
        'chunk_count',
        'error_message',
    ];

    /**
     * Get the semantic chunks associated with this document.
     */
    public function chunks(): HasMany
    {
        return $this->hasMany(DocumentationChunk::class);
    }
}
