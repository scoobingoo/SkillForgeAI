<?php

// SPDX-License-Identifier: Apache-2.0
// Job xử lý mục tiêu công việc (Role mẫu hoặc JD tự do), kiểm chứng bằng chứng nguyên văn và lưu job_skills

namespace App\Jobs;

use App\Models\JobSkill;
use App\Models\JobTarget;
use App\Models\JobTracking;
use App\Models\Skill;
use App\Services\Llm\LlmProviderInterface;
use App\Services\Llm\PromptLoader;
use App\Services\SkillNormalizerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Yaml\Yaml;
use Throwable;

class ProcessJobTargetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $jobTargetId,
        public readonly string $trackingId,
    ) {}

    /**
     * Xử lý trích xuất yêu cầu kỹ năng từ Role mẫu (data/roles/*.yaml) hoặc văn bản JD tự do.
     */
    public function handle(LlmProviderInterface $llm, SkillNormalizerService $normalizer): void
    {
        $tracking = JobTracking::find($this->trackingId);
        $jobTarget = JobTarget::find($this->jobTargetId);

        if (! $jobTarget) {
            $tracking?->update(['state' => 'failed', 'error' => 'Không tìm thấy mục tiêu công việc.']);

            return;
        }

        try {
            $tracking?->update(['state' => 'active', 'progress' => 30]);
            $allSkills = Skill::all();
            JobSkill::where('job_target_id', $jobTarget->id)->delete();

            // Trường hợp 1: Người dùng chọn vị trí mẫu (role_slug) từ data/roles/*.yaml
            if (! empty($jobTarget->role_slug)) {
                $roleFile = base_path("data/roles/{$jobTarget->role_slug}.yaml");
                if (is_file($roleFile)) {
                    $roleData = Yaml::parseFile($roleFile);
                    $requirements = $roleData['skills'] ?? [];

                    foreach ($requirements as $req) {
                        $slug = (string) ($req['slug'] ?? '');
                        $skillModel = $allSkills->firstWhere('slug', $slug);
                        $name = $skillModel?->name ?? (string) ($req['name'] ?? $slug);

                        JobSkill::create([
                            'job_target_id' => $jobTarget->id,
                            'skill_id' => $skillModel?->id,
                            'raw_name' => $name,
                            'canonical_slug' => $slug,
                            'category' => $skillModel?->category ?? (string) ($req['category'] ?? 'general'),
                            'level' => (string) ($req['level'] ?? 'intermediate'),
                            'years' => isset($req['years']) ? (float) $req['years'] : null,
                            'importance' => (string) ($req['importance'] ?? 'required'),
                            'evidence' => (string) ($req['evidence'] ?? "Yêu cầu kỹ năng {$name} ({$req['level']}) cho vị trí {$jobTarget->title}."),
                            'mapping' => 'alias',
                        ]);
                    }

                    $jobTarget->update(['status' => 'ready']);
                    $tracking?->update(['state' => 'completed', 'progress' => 100]);

                    return;
                }
            }

            // Trường hợp 2: Văn bản JD tự do -> Trích xuất qua LLM + kiểm tra evidence nguyên văn
            $prompt = PromptLoader::load('extract-jd', [
                'jd_text' => $jobTarget->jd_text,
            ]);

            $extracted = $llm->generateObject(
                jsonSchema: [
                    'type' => 'object',
                    'properties' => [
                        'requirements' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'name' => ['type' => 'string'],
                                    'level' => ['type' => ['string', 'null'], 'enum' => ['beginner', 'intermediate', 'advanced', null]],
                                    'years' => ['type' => ['number', 'null']],
                                    'importance' => ['type' => 'string', 'enum' => ['required', 'nice_to_have']],
                                    'evidence' => ['type' => 'string'],
                                ],
                                'required' => ['name', 'importance', 'evidence'],
                            ],
                        ],
                    ],
                    'required' => ['requirements'],
                ],
                validator: function (array $data): array {
                    if (! isset($data['requirements']) || ! is_array($data['requirements'])) {
                        throw new \InvalidArgumentException('Thiếu mảng requirements trong kết quả trích xuất JD.');
                    }

                    return $data;
                },
                messages: [
                    ['role' => 'user', 'content' => $prompt],
                ]
            );

            $tracking?->update(['progress' => 70]);

            foreach ($extracted['requirements'] as $req) {
                $rawName = trim((string) ($req['name'] ?? ''));
                $evidence = trim((string) ($req['evidence'] ?? ''));

                if ($rawName === '' || $evidence === '') {
                    continue;
                }

                // Kiểm tra đoạn trích evidence phải tồn tại thực sự trong văn bản JD gốc để chống ảo giác
                if (! SkillNormalizerService::verifyEvidenceInSource($evidence, $jobTarget->jd_text)) {
                    continue;
                }

                $normResult = $normalizer->normalizeSkill($rawName, $allSkills);
                $importance = ($req['importance'] ?? 'required') === 'nice_to_have' ? 'nice_to_have' : 'required';
                $level = in_array($req['level'] ?? null, ['beginner', 'intermediate', 'advanced'], true)
                    ? $req['level']
                    : 'intermediate';

                JobSkill::create([
                    'job_target_id' => $jobTarget->id,
                    'skill_id' => $normResult['skill']?->id,
                    'raw_name' => $rawName,
                    'canonical_slug' => $normResult['canonical_slug'] ?? strtolower(preg_replace('/[^a-z0-9]+/i', '-', $rawName)),
                    'category' => $normResult['category'],
                    'level' => $level,
                    'years' => isset($req['years']) && is_numeric($req['years']) ? (float) $req['years'] : null,
                    'importance' => $importance,
                    'evidence' => $evidence,
                    'mapping' => $normResult['mapping'],
                ]);
            }

            $jobTarget->update(['status' => 'ready']);
            $tracking?->update(['state' => 'completed', 'progress' => 100]);
        } catch (Throwable $e) {
            $jobTarget->update(['status' => 'failed']);
            $tracking?->update([
                'state' => 'failed',
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
