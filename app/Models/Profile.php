<?php

// SPDX-License-Identifier: Apache-2.0
// Model biểu diễn hồ sơ ứng viên từ CV đã ẩn thông tin cá nhân (bảng profiles)

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Profile extends Model
{
    use HasUuids;

    protected $fillable = [
        'title',
        'source_type',
        'sample_id',
        'raw_text',
        'status',
    ];

    /**
     * Danh sách kỹ năng trích xuất được từ CV của ứng viên.
     */
    public function skills(): HasMany
    {
        return $this->hasMany(ProfileSkill::class, 'profile_id');
    }

    /**
     * Danh sách báo cáo khoảng cách kỹ năng (Skill Gap Reports) của hồ sơ này.
     */
    public function gapReports(): HasMany
    {
        return $this->hasMany(GapReport::class, 'profile_id');
    }
}
