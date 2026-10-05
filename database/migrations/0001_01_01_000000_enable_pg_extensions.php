<?php

// SPDX-License-Identifier: Apache-2.0
// Bật các extension bắt buộc trên PostgreSQL 17: vector (pgvector), unaccent, pgcrypto

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Thực thi migration: kích hoạt các extension trên PostgreSQL.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector;');
            DB::statement('CREATE EXTENSION IF NOT EXISTS unaccent;');
            DB::statement('CREATE EXTENSION IF NOT EXISTS pgcrypto;');
        }
    }

    /**
     * Hoàn tác migration.
     */
    public function down(): void
    {
        // Không gỡ extension hệ thống khi rollback để tránh ảnh hưởng dữ liệu khác
    }
};
