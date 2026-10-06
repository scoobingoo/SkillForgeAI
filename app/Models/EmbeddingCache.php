<?php

// SPDX-License-Identifier: Apache-2.0
// Model lưu bộ nhớ đệm vector embedding theo mã băm SHA-256 (bảng embedding_cache) và theo dõi tiến trình Job (bảng job_trackings)

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class EmbeddingCache extends Model
{
    use HasUuids;

    protected $table = 'embedding_cache';

    protected $fillable = [
        'text_hash',
        'model',
        'embedding_json',
    ];

    protected $casts = [
        'embedding_json' => 'array',
    ];
}
