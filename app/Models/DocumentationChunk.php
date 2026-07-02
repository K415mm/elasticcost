<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentationChunk extends Model
{
    protected $table = 'documentation_chunks';

    protected $fillable = [
        'document_id',
        'source_file',
        'chunk_text',
        'embedding',
    ];

    protected $casts = [
        'embedding' => 'array',
    ];

    /**
     * Get the document this chunk belongs to.
     */
    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * Compute cosine similarity (dot product since vectors are normalized)
     * between this chunk's embedding and a query vector.
     *
     * @param  array<float>  $queryVector
     */
    public function similarity(array $queryVector): float
    {
        $vector = $this->embedding;

        if (! is_array($vector) || empty($vector)) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $count = min(count($vector), count($queryVector));

        for ($i = 0; $i < $count; $i++) {
            $dotProduct += $vector[$i] * $queryVector[$i];
        }

        return $dotProduct;
    }
}
