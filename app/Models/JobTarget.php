<?php

// SPDX-License-Identifier: Apache-2.0
// Model biểu diễn vị trí tuyển dụng mục tiêu hoặc mô tả công việc JD (bảng job_targets)

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobTarget extends Model
{
    use HasUuids;

    protected $fillable = [
        'title',
        'role_slug',
        'jd_text',
        'status',
    ];

    /**
     * Danh sách kỹ năng yêu cầu được trích xuất từ JD hoặc Role mẫu.
     */
    public function skills(): HasMany
    {
        return $this->hasMany(JobSkill::class, 'job_target_id');
    }
}
