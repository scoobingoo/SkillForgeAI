<?php

// SPDX-License-Identifier: Apache-2.0
// Model biểu diễn một kỹ năng chuẩn trong Taxonomy (bảng skills)

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Skill extends Model
{
    use HasUuids;

    protected $fillable = [
        'slug',
        'name',
        'category',
        'aliases',
        'parent_slug',
        'related',
        'embedding_json',
    ];

    protected $casts = [
        'aliases' => 'array',
        'related' => 'array',
        'embedding_json' => 'array',
    ];
}
