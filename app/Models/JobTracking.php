<?php

// SPDX-License-Identifier: Apache-2.0
// Model theo dõi trạng thái xử lý bất đồng bộ cho endpoint GET /api/v1/jobs/:id

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class JobTracking extends Model
{
    use HasUuids;

    protected $table = 'job_trackings';

    protected $fillable = [
        'type',
        'state',
        'progress',
        'entity_id',
        'error',
    ];

    protected $casts = [
        'progress' => 'integer',
    ];
}
