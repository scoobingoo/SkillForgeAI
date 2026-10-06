<?php

// SPDX-License-Identifier: Apache-2.0
// Model biểu diễn kỹ năng trích xuất từ CV kèm bằng chứng nguyên văn (bảng profile_skills)

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfileSkill extends Model
{
    use HasUuids;

    protected $fillable = [
        'profile_id',
        'skill_id',
        'raw_name',
        'canonical_slug',
        'category',
        'level',
        'years',
        'evidence',
        'mapping',
    ];

    protected $casts = [
        'years' => 'float',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'profile_id');
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class, 'skill_id');
    }
}
