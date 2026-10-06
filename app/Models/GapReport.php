<?php

// SPDX-License-Identifier: Apache-2.0
// Model biểu diễn báo cáo khoảng cách kỹ năng (Skill Gap Report) giữa Profile và JobTarget (bảng gap_reports)

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class GapReport extends Model
{
    use HasUuids;

    protected $fillable = [
        'profile_id',
        'job_target_id',
        'match_score',
        'category_scores',
        'matched_skills',
        'gaps',
        'status',
    ];

    protected $casts = [
        'match_score' => 'float',
        'category_scores' => 'array',
        'matched_skills' => 'array',
        'gaps' => 'array',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'profile_id');
    }

    public function jobTarget(): BelongsTo
    {
        return $this->belongsTo(JobTarget::class, 'job_target_id');
    }

    public function roadmap(): HasOne
    {
        return $this->hasOne(Roadmap::class, 'gap_report_id')->latestOfMany();
    }

    public function interviewSessions(): HasMany
    {
        return $this->hasMany(InterviewSession::class, 'gap_report_id');
    }
}
