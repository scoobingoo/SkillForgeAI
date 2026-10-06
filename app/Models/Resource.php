<?php

// SPDX-License-Identifier: Apache-2.0
// Model biểu diễn tài nguyên học tập mở trong kho tri thức RAG (bảng resources)

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Resource extends Model
{
    use HasUuids;

    protected $fillable = [
        'slug',
        'title',
        'skills',
        'level',
        'language',
        'url',
        'license',
        'est_hours',
    ];

    protected $casts = [
        'skills' => 'array',
        'est_hours' => 'float',
    ];

    public function chunks(): HasMany
    {
        return $this->hasMany(ResourceChunk::class, 'resource_id');
    }
}
