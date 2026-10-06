<?php

// SPDX-License-Identifier: Apache-2.0
// Model biểu diễn lộ trình học tập cá nhân hoá theo tuần (bảng roadmaps)

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Roadmap extends Model
{
    use HasUuids;

    protected $fillable = [
        'gap_report_id',
        'hours_per_week',
        'total_weeks',
        'weeks',
    ];

    protected $casts = [
        'hours_per_week' => 'integer',
        'total_weeks' => 'integer',
        'weeks' => 'array',
    ];

    public function gapReport(): BelongsTo
    {
        return $this->belongsTo(GapReport::class, 'gap_report_id');
    }
}
