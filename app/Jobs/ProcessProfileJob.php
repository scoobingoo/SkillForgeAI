<?php

// SPDX-License-Identifier: Apache-2.0
// Job trích xuất kỹ năng từ CV, kiểm chứng đoạn trích nguyên văn (evidence) và chuẩn hoá kỹ năng 3 tầng

namespace App\Jobs;

use App\Models\JobTracking;
use App\Models\Profile;
use App\Models\ProfileSkill;
use App\Models\Skill;
use App\Services\Llm\LlmProviderInterface;
use App\Services\Llm\PromptLoader;
use App\Services\SkillNormalizerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessProfileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $profileId,
        public readonly string $trackingId,
    ) {}

    /**
     * Thực thi trích xuất kỹ năng từ CV, kiểm tra evidence có thật trong văn bản gốc và chuẩn hoá về Taxonomy.
     */
    public function handle(LlmProviderInterface $llm, SkillNormalizerService $normalizer): void
    {
        $tracking = JobTracking::find($this->trackingId);
        $profile = Profile::find($this->profileId);

        if (! $profile) {
            $tracking?->update(['state' => 'failed', 'error' => 'Không tìm thấy hồ sơ CV.']);

            return;
        }

        try {
            $tracking?->update(['state' => 'active', 'progress' => 25]);

            $prompt = PromptLoader::load('extract-cv', [
                'cv_text' => $profile->raw_text,
            ]);

            $extracted = $llm->generateObject(
                jsonSchema: [
                    'type' => 'object',
                    'properties' => [
                        'skills' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'name' => ['type' => 'string'],
                                    'level' => ['type' => ['string', 'null'], 'enum' => ['beginner', 'intermediate', 'advanced', null]],
                                    'years' => ['type' => ['number', 'null']],
                                    'evidence' => ['type' => 'string'],
                                ],
                                'required' => ['name', 'evidence'],
                            ],
                        ],
                    ],
                    'required' => ['skills'],
                ],
                validator: function (array $data): array {
                    if (! isset($data['skills']) || ! is_array($data['skills'])) {
                        throw new \InvalidArgumentException('Thiếu mảng skills trong kết quả trích xuất CV.');
                    }

                    return $data;
                },
                messages: [
                    ['role' => 'user', 'content' => $prompt],
                ]
            );

            $tracking?->update(['progress' => 65]);

            $allSkills = Skill::all();
            ProfileSkill::where('profile_id', $profile->id)->delete();

            foreach ($extracted['skills'] as $item) {
                $rawName = trim((string) ($item['name'] ?? ''));
                $evidence = trim((string) ($item['evidence'] ?? ''));

                if ($rawName === '' || $evidence === '') {
                    continue;
                }

                // Kiểm chứng nghiêm ngặt: loại bỏ mọi kỹ năng có evidence không xuất hiện nguyên văn trong CV gốc
                if (! SkillNormalizerService::verifyEvidenceInSource($evidence, $profile->raw_text)) {
                    continue;
                }

                $normResult = $normalizer->normalizeSkill($rawName, $allSkills);
                $level = in_array($item['level'] ?? null, ['beginner', 'intermediate', 'advanced'], true)
                    ? $item['level']
                    : 'intermediate';

                ProfileSkill::create([
                    'profile_id' => $profile->id,
                    'skill_id' => $normResult['skill']?->id,
                    'raw_name' => $rawName,
                    'canonical_slug' => $normResult['canonical_slug'] ?? strtolower(preg_replace('/[^a-z0-9]+/i', '-', $rawName)),
                    'category' => $normResult['category'],
                    'level' => $level,
                    'years' => isset($item['years']) && is_numeric($item['years']) ? (float) $item['years'] : null,
                    'evidence' => $evidence,
                    'mapping' => $normResult['mapping'],
                ]);
            }

            $profile->update(['status' => 'ready']);
            $tracking?->update(['state' => 'completed', 'progress' => 100]);
        } catch (Throwable $e) {
            $profile->update(['status' => 'failed']);
            $tracking?->update([
                'state' => 'failed',
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
