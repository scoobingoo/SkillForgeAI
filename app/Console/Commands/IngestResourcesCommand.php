<?php

// SPDX-License-Identifier: Apache-2.0
// Lệnh Artisan nạp tài nguyên học tập từ data/resources/*.md vào kho học liệu Hybrid RAG

namespace App\Console\Commands;

use App\Models\Resource;
use App\Models\ResourceChunk;
use App\Services\Llm\LlmProviderInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\Yaml\Yaml;

class IngestResourcesCommand extends Command
{
    protected $signature = 'app:ingest';

    protected $description = 'Đọc data/resources/*.md, kiểm tra frontmatter, chia chunk theo heading ##, sinh vector bge-m3 và upsert idempotent.';

    public function handle(LlmProviderInterface $llm): int
    {
        $files = glob(base_path('data/resources/*.md')) ?: [];
        if (empty($files)) {
            $this->warn('Không tìm thấy file tài nguyên nào trong data/resources/*.md');

            return self::SUCCESS;
        }

        $isPgsql = DB::connection()->getDriverName() === 'pgsql';
        $ingestedCount = 0;
        $skippedChunks = 0;

        foreach ($files as $filePath) {
            $raw = (string) file_get_contents($filePath);
            if (! preg_match('/^---\s*\R(.*?)\R---\s*\R(.*)$/s', $raw, $matches)) {
                $this->error("Bỏ qua file thiếu YAML frontmatter: {$filePath}");

                continue;
            }

            $frontmatter = Yaml::parse($matches[1]);
            $markdownBody = trim($matches[2]);

            // 1. Kiểm tra tính hợp lệ của YAML frontmatter bắt buộc trên mỗi học liệu mở
            $validator = Validator::make(is_array($frontmatter) ? $frontmatter : [], [
                'slug' => ['required', 'string'],
                'title' => ['required', 'string'],
                'skills' => ['required', 'array', 'min:1'],
                'level' => ['required', 'string', 'in:beginner,intermediate,advanced'],
                'language' => ['required', 'string'],
                'url' => ['required', 'url'],
                'license' => ['required', 'string'],
                'est_hours' => ['required', 'numeric', 'min:0.5'],
            ]);

            if ($validator->fails()) {
                $this->error("Frontmatter không hợp lệ tại {$filePath}: ".json_encode($validator->errors()->toArray(), JSON_UNESCAPED_UNICODE));

                continue;
            }

            /** @var resource $resource */
            $resource = Resource::updateOrCreate(
                ['slug' => $frontmatter['slug']],
                [
                    'title' => $frontmatter['title'],
                    'skills' => array_values($frontmatter['skills']),
                    'level' => $frontmatter['level'],
                    'language' => $frontmatter['language'],
                    'url' => $frontmatter['url'],
                    'license' => $frontmatter['license'],
                    'est_hours' => (float) $frontmatter['est_hours'],
                ]
            );

            // 2. Chia chunk theo heading ##, tối đa ~500 token, chồng lấn 50 token
            $chunks = $this->splitMarkdownIntoChunks($markdownBody);

            foreach ($chunks as $chunkIndex => $chunkData) {
                $contentHash = hash('sha256', $chunkData['heading']."\n".$chunkData['content']);

                $existing = ResourceChunk::where('resource_slug', $resource->slug)
                    ->where('chunk_index', $chunkIndex)
                    ->first();

                // 4. Bỏ qua chunk có content_hash không đổi (idempotent)
                if ($existing && $existing->content_hash === $contentHash) {
                    $skippedChunks++;

                    continue;
                }

                $vectors = $llm->embed([$chunkData['heading']."\n".$chunkData['content']]);
                $embedding = $vectors[0] ?? [];

                $chunkModel = ResourceChunk::updateOrCreate(
                    [
                        'resource_slug' => $resource->slug,
                        'chunk_index' => $chunkIndex,
                    ],
                    [
                        'resource_id' => $resource->id,
                        'heading' => $chunkData['heading'],
                        'content' => $chunkData['content'],
                        'content_hash' => $contentHash,
                        'skills' => $resource->skills,
                        'embedding_json' => $embedding,
                    ]
                );

                // Cập nhật cột vector(1024) và tsvector (simple + unaccent) nếu chạy trên PostgreSQL 17
                if ($isPgsql && ! empty($embedding)) {
                    $vectorLiteral = '['.implode(',', $embedding).']';
                    DB::statement(
                        'UPDATE resource_chunks SET embedding = ?::vector, tsv = to_tsvector(\'simple\', unaccent(coalesce(heading, \'\') || \' \' || content)) WHERE id = ?',
                        [$vectorLiteral, $chunkModel->id]
                    );
                }

                $ingestedCount++;
            }
        }

        $this->info("Hoàn tất ingest: Đã nạp/cập nhật {$ingestedCount} chunks, bỏ qua {$skippedChunks} chunks không đổi.");

        return self::SUCCESS;
    }

    /**
     * Chia nội dung Markdown theo heading `##`, giới hạn khoảng 500 từ/token với chồng lấn 50 từ.
     *
     * @return array<int, array{heading: string, content: string}>
     */
    private function splitMarkdownIntoChunks(string $markdown): array
    {
        $sections = preg_split('/(?=^##\s+)/m', $markdown, -1, PREG_SPLIT_NO_EMPTY) ?: [$markdown];
        $chunks = [];

        foreach ($sections as $section) {
            $lines = explode("\n", trim($section));
            $heading = 'Tổng quan';
            if (isset($lines[0]) && str_starts_with(trim($lines[0]), '##')) {
                $heading = trim(ltrim(array_shift($lines), '#'));
            }

            $body = trim(implode("\n", $lines));
            if ($body === '') {
                continue;
            }

            $words = preg_split('/\s+/u', $body, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            if (count($words) <= 500) {
                $chunks[] = [
                    'heading' => $heading,
                    'content' => $body,
                ];
            } else {
                // Chia cửa sổ trượt 500 từ, chồng lấn 50 từ
                $step = 450;
                for ($start = 0; $start < count($words); $start += $step) {
                    $slice = array_slice($words, $start, 500);
                    if (empty($slice)) {
                        break;
                    }
                    $chunks[] = [
                        'heading' => $heading,
                        'content' => implode(' ', $slice),
                    ];
                }
            }
        }

        return $chunks;
    }
}
