<?php

// SPDX-License-Identifier: Apache-2.0
// Migration khởi tạo đầy đủ 12 bảng nghiệp vụ của SkillForge AI cùng các bảng hạ tầng Queue/Session/Cache

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Thực thi migration tạo toàn bộ các bảng với khoá chính UUID v7 và xoá cascade.
     */
    public function up(): void
    {
        $isPgsql = DB::connection()->getDriverName() === 'pgsql';

        // Bảng hạ tầng Laravel: sessions, cache, jobs, job_trackings
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });

        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });

        // Bảng theo dõi trạng thái tiến trình bất đồng bộ cho endpoint GET /api/v1/jobs/:id
        Schema::create('job_trackings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 64); // profile_extract | jd_extract | gap_report
            $table->string('state', 32)->default('queued'); // queued | active | completed | failed
            $table->unsignedTinyInteger('progress')->default(0);
            $table->uuid('entity_id')->nullable()->index();
            $table->text('error')->nullable();
            $table->timestamps();
        });

        // 1. Bảng skills (Taxonomy kỹ năng chuẩn)
        Schema::create('skills', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug', 120)->unique();
            $table->string('name', 160);
            $table->string('category', 64)->index(); // frontend, backend, mobile, data, devops, cloud, testing, soft-skill
            $table->json('aliases')->nullable();
            $table->string('parent_slug', 120)->nullable()->index();
            $table->json('related')->nullable();
            // Lưu vector dạng JSON để tương thích mọi DB và thêm cột vector(1024) gốc khi chạy PostgreSQL
            $table->json('embedding_json')->nullable();
            $table->timestamps();
        });

        if ($isPgsql) {
            DB::statement('ALTER TABLE skills ADD COLUMN IF NOT EXISTS embedding vector(1024);');
            DB::statement('CREATE INDEX IF NOT EXISTS skills_embedding_hnsw_idx ON skills USING hnsw (embedding vector_cosine_ops);');
        }

        // 2. Bảng profiles (Hồ sơ ứng viên từ CV đã ẩn PII)
        Schema::create('profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title', 200)->nullable();
            $table->string('source_type', 32)->default('upload'); // upload | sample
            $table->string('sample_id', 80)->nullable();
            $table->longText('raw_text'); // Văn bản CV sau khi đã che email và số điện thoại
            $table->string('status', 32)->default('processing'); // processing | ready | failed
            $table->timestamps();
        });

        // 3. Bảng profile_skills (Kỹ năng trích xuất từ CV, xoá cascade theo profile)
        Schema::create('profile_skills', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->foreignUuid('skill_id')->nullable()->constrained('skills')->nullOnDelete();
            $table->string('raw_name', 160);
            $table->string('canonical_slug', 120)->nullable()->index();
            $table->string('category', 64)->default('general');
            $table->string('level', 32)->nullable(); // beginner | intermediate | advanced
            $table->decimal('years', 4, 1)->nullable();
            $table->text('evidence'); // Đoạn trích nguyên văn từ CV
            $table->string('mapping', 32)->default('unmapped'); // alias | vector | llm | unmapped
            $table->timestamps();
        });

        // 4. Bảng job_targets (Mục tiêu công việc từ Role mẫu hoặc JD dán vào)
        Schema::create('job_targets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title', 200);
            $table->string('role_slug', 120)->nullable()->index();
            $table->longText('jd_text');
            $table->string('status', 32)->default('processing'); // processing | ready | failed
            $table->timestamps();
        });

        // 5. Bảng job_skills (Yêu cầu kỹ năng trích xuất từ JD)
        Schema::create('job_skills', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('job_target_id')->constrained('job_targets')->cascadeOnDelete();
            $table->foreignUuid('skill_id')->nullable()->constrained('skills')->nullOnDelete();
            $table->string('raw_name', 160);
            $table->string('canonical_slug', 120)->nullable()->index();
            $table->string('category', 64)->default('general');
            $table->string('level', 32)->nullable(); // beginner | intermediate | advanced
            $table->decimal('years', 4, 1)->nullable();
            $table->string('importance', 32)->default('required'); // required | nice_to_have
            $table->text('evidence'); // Đoạn trích nguyên văn từ JD
            $table->string('mapping', 32)->default('unmapped'); // alias | vector | llm | unmapped
            $table->timestamps();
        });

        // 6. Bảng gap_reports (Báo cáo khoảng cách kỹ năng, xoá cascade khi xoá profile)
        Schema::create('gap_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->foreignUuid('job_target_id')->constrained('job_targets')->cascadeOnDelete();
            $table->decimal('match_score', 5, 4)->default(0); // Điểm tương thích từ 0.0000 đến 1.0000
            $table->json('category_scores')->nullable(); // Điểm theo từng nhóm kỹ năng để vẽ Radar Chart
            $table->json('matched_skills')->nullable(); // Danh sách kỹ năng đã đáp ứng
            $table->json('gaps')->nullable(); // Danh sách gap kèm jdEvidence, cvEvidence, explanation
            $table->string('status', 32)->default('processing'); // processing | ready | failed
            $table->timestamps();
        });

        // 7. Bảng roadmaps (Lộ trình học tập theo tuần, xoá cascade khi xoá profile/gap_report)
        Schema::create('roadmaps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('gap_report_id')->constrained('gap_reports')->cascadeOnDelete();
            $table->unsignedSmallInteger('hours_per_week')->default(10);
            $table->unsignedSmallInteger('total_weeks')->default(4);
            $table->json('weeks'); // Mảng weeks[]{ week, focusSkills[], items[]{ resourceSlug, title, url, task, estHours, completed } }
            $table->timestamps();
        });

        // 8. Bảng resources (Tài nguyên học tập gốc từ data/resources/*.md)
        Schema::create('resources', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug', 160)->unique();
            $table->string('title', 255);
            $table->json('skills'); // Danh sách skill slugs liên quan
            $table->string('level', 32)->default('beginner');
            $table->string('language', 16)->default('vi');
            $table->string('url', 500);
            $table->string('license', 64);
            $table->decimal('est_hours', 5, 1)->default(2.0);
            $table->timestamps();
        });

        // 9. Bảng resource_chunks (Các phân đoạn tài liệu phục vụ Hybrid Search RAG)
        Schema::create('resource_chunks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('resource_id')->constrained('resources')->cascadeOnDelete();
            $table->string('resource_slug', 160)->index();
            $table->unsignedInteger('chunk_index');
            $table->string('heading', 255)->nullable();
            $table->text('content');
            $table->string('content_hash', 64)->index();
            $table->json('skills');
            $table->json('embedding_json')->nullable();
            $table->timestamps();

            $table->unique(['resource_slug', 'chunk_index']);
        });

        if ($isPgsql) {
            DB::statement('ALTER TABLE resource_chunks ADD COLUMN IF NOT EXISTS embedding vector(1024);');
            DB::statement('ALTER TABLE resource_chunks ADD COLUMN IF NOT EXISTS tsv tsvector;');
            DB::statement('CREATE INDEX IF NOT EXISTS resource_chunks_embedding_hnsw_idx ON resource_chunks USING hnsw (embedding vector_cosine_ops);');
            DB::statement('CREATE INDEX IF NOT EXISTS resource_chunks_tsv_gin_idx ON resource_chunks USING gin (tsv);');
            DB::statement('CREATE INDEX IF NOT EXISTS resource_chunks_skills_gin_idx ON resource_chunks USING gin ((skills::jsonb));');
        }

        // 10. Bảng interview_sessions (Phiên phỏng vấn thử thích ứng, xoá cascade theo gap_report/profile)
        Schema::create('interview_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('gap_report_id')->constrained('gap_reports')->cascadeOnDelete();
            $table->unsignedTinyInteger('question_count')->default(5);
            $table->json('types'); // ['concept', 'scenario', 'behavioral', 'coding_discussion']
            $table->string('status', 32)->default('in_progress'); // in_progress | completed
            $table->decimal('average_score', 4, 2)->nullable();
            $table->text('summary')->nullable();
            $table->timestamps();
        });

        // 11. Bảng interview_turns (Từng lượt hỏi - đáp và chấm điểm Rubric trong phiên phỏng vấn)
        Schema::create('interview_turns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('interview_session_id')->constrained('interview_sessions')->cascadeOnDelete();
            $table->unsignedTinyInteger('turn_number');
            $table->string('skill_slug', 120);
            $table->string('question_type', 40)->default('concept');
            $table->string('difficulty', 32)->default('intermediate'); // easy | intermediate | hard
            $table->text('question');
            $table->text('reference_answer')->nullable(); // Đáp án tham chiếu sinh trước khi chấm điểm khách quan
            $table->text('user_answer')->nullable();
            $table->unsignedTinyInteger('score')->nullable(); // 1..5
            $table->json('grade')->nullable(); // { score, criteria: { correctness, depth, clarity }, strengths[], missing_points[], sample_answer }
            $table->timestamps();
        });

        // 12. Bảng embedding_cache (Lưu đệm vector embedding tránh tính lại chuỗi trùng lặp)
        Schema::create('embedding_cache', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('text_hash', 64)->unique();
            $table->string('model', 80);
            $table->json('embedding_json');
            $table->timestamps();
        });
    }

    /**
     * Hoàn tác migration: xoá các bảng theo thứ tự phụ thuộc khoá ngoại.
     */
    public function down(): void
    {
        Schema::dropIfExists('embedding_cache');
        Schema::dropIfExists('interview_turns');
        Schema::dropIfExists('interview_sessions');
        Schema::dropIfExists('resource_chunks');
        Schema::dropIfExists('resources');
        Schema::dropIfExists('roadmaps');
        Schema::dropIfExists('gap_reports');
        Schema::dropIfExists('job_skills');
        Schema::dropIfExists('job_targets');
        Schema::dropIfExists('profile_skills');
        Schema::dropIfExists('profiles');
        Schema::dropIfExists('skills');
        Schema::dropIfExists('job_trackings');
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('sessions');
    }
};
