<?php

// SPDX-License-Identifier: Apache-2.0
// Model biểu diễn phân đoạn tài liệu (chunk) phục vụ truy xuất Hybrid Search RAG (bảng resource_chunks)

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResourceChunk extends Model
{
    use HasUuids;

    protected $fillable = [
        'resource_id',
        'resource_slug',
        'chunk_index',
        'heading',
        'content',
        'content_hash',
        'skills',
        'embedding_json',
    ];

    protected $casts = [
        'chunk_index' => 'integer',
        'skills' => 'array',
        'embedding_json' => 'array',
    ];

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class, 'resource_id');
    }
}
