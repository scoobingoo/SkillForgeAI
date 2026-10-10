<?php

// SPDX-License-Identifier: Apache-2.0
// Seeder nạp danh mục kỹ năng chuẩn từ data/skills.yaml và sinh vector embedding cho toàn bộ Taxonomy

namespace Database\Seeders;

use App\Models\Skill;
use App\Services\Llm\LlmProviderInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;
use Throwable;

class DatabaseSeeder extends Seeder
{
    /**
     * Nạp dữ liệu mở từ thư mục data/ vào cơ sở dữ liệu.
     */
    public function run(LlmProviderInterface $llm): void
    {
        $skillsFile = base_path('data/skills.yaml');
        if (! is_file($skillsFile)) {
            throw new RuntimeException("Không tìm thấy file {$skillsFile}");
        }

        $parsed = Yaml::parseFile($skillsFile);
        $skillsList = $parsed['skills'] ?? [];

        $isPgsql = DB::connection()->getDriverName() === 'pgsql';

        foreach ($skillsList as $item) {
            // Kiểm tra cấu trúc hợp lệ của từng mục kỹ năng trong data/skills.yaml
            $validator = Validator::make(is_array($item) ? $item : [], [
                'slug' => ['required', 'string'],
                'name' => ['required', 'string'],
                'category' => ['required', 'string', 'in:frontend,backend,mobile,data,devops,cloud,testing,soft-skill'],
                'aliases' => ['nullable', 'array'],
                'parent' => ['nullable', 'string'],
                'related' => ['nullable', 'array'],
            ]);

            if ($validator->fails()) {
                throw new RuntimeException('Dữ liệu kỹ năng không hợp lệ: '.json_encode($validator->errors()->toArray(), JSON_UNESCAPED_UNICODE));
            }

            $textForEmbedding = $item['name'].' '.implode(' ', $item['aliases'] ?? []);
            try {
                $vectors = $llm->embed([$textForEmbedding]);
                $embedding = $vectors[0] ?? [];
            } catch (Throwable) {
                $embedding = [];
            }

            $skillModel = Skill::updateOrCreate(
                ['slug' => $item['slug']],
                [
                    'name' => $item['name'],
                    'category' => $item['category'],
                    'aliases' => $item['aliases'] ?? [],
                    'parent_slug' => $item['parent'] ?? null,
                    'related' => $item['related'] ?? [],
                    'embedding_json' => $embedding,
                ]
            );

            if ($isPgsql && ! empty($embedding)) {
                $vectorLiteral = '['.implode(',', $embedding).']';
                DB::statement('UPDATE skills SET embedding = ?::vector WHERE id = ?', [$vectorLiteral, $skillModel->id]);
            }
        }

        // Kiểm tra tính hợp lệ của toàn bộ file data/roles/*.yaml
        foreach (glob(base_path('data/roles/*.yaml')) ?: [] as $roleFile) {
            $roleData = Yaml::parseFile($roleFile);
            $validator = Validator::make(is_array($roleData) ? $roleData : [], [
                'slug' => ['required', 'string'],
                'title' => ['required', 'string'],
                'skills' => ['required', 'array', 'min:1'],
            ]);

            if ($validator->fails()) {
                throw new RuntimeException("File role {$roleFile} không hợp lệ: ".json_encode($validator->errors()->toArray(), JSON_UNESCAPED_UNICODE));
            }
        }

        // Gọi lệnh ingest để nạp luôn các tài liệu RAG trong data/resources/*.md
        try {
            Artisan::call('app:ingest');
        } catch (Throwable) {
            // Cho phép tiếp tục nếu Ollama đang tải model ở lần khởi động đầu tiên
        }
    }
}
