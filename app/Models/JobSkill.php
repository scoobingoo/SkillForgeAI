<?php

// SPDX-License-Identifier: Apache-2.0
// Model biểu diễn kỹ năng yêu cầu trong JD kèm mức độ quan trọng và trích dẫn (bảng job_skills)

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobSkill extends Model
{
    use HasUuids;

    protected $fillable = [
        'job_target_id',
        'skill_id',
        'raw_name',
        'canonical_slug',
        'category',
        'level',
        'years',
        'importance',
        'evidence',
        'mapping',
    ];

    protected $casts = [
        'years' => 'float',
    ];

    public function jobTarget(): BelongsTo
    {
        return $this->belongsTo(JobTarget::class, 'job_target_id');
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class, 'skill_id');
    }
}
