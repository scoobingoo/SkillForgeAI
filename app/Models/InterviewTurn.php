<?php

// SPDX-License-Identifier: Apache-2.0
// Model biểu diễn từng lượt hỏi - trả lời và kết quả chấm điểm Rubric trong phiên phỏng vấn (bảng interview_turns)

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InterviewTurn extends Model
{
    use HasUuids;

    protected $fillable = [
        'interview_session_id',
        'turn_number',
        'skill_slug',
        'question_type',
        'difficulty',
        'question',
        'reference_answer',
        'user_answer',
        'score',
        'grade',
    ];

    protected $casts = [
        'turn_number' => 'integer',
        'score' => 'integer',
        'grade' => 'array',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(InterviewSession::class, 'interview_session_id');
    }
}
