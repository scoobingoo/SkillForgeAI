<?php

// SPDX-License-Identifier: Apache-2.0
// Model biểu diễn phiên phỏng vấn thử thích ứng (bảng interview_sessions)

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InterviewSession extends Model
{
    use HasUuids;

    protected $fillable = [
        'gap_report_id',
        'question_count',
        'types',
        'status',
        'average_score',
        'summary',
    ];

    protected $casts = [
        'question_count' => 'integer',
        'types' => 'array',
        'average_score' => 'float',
    ];

    public function gapReport(): BelongsTo
    {
        return $this->belongsTo(GapReport::class, 'gap_report_id');
    }

    public function turns(): HasMany
    {
        return $this->hasMany(InterviewTurn::class, 'interview_session_id')->orderBy('turn_number');
    }
}
