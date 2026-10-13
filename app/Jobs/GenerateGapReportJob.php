<?php

// SPDX-License-Identifier: Apache-2.0
// Job tính toán điểm đáp ứng bằng ScoringService và sinh giải thích ngắn dựa trên bằng chứng cho từng khoảng cách kỹ năng

namespace App\Jobs;

use App\Models\GapReport;
use App\Models\JobTracking;
use App\Models\Skill;
use App\Services\Llm\LlmProviderInterface;
use App\Services\Llm\PromptLoader;
use App\Services\ScoringService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GenerateGapReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $reportId,
        public readonly string $trackingId,
    ) {}

    /**
     * Tính toán báo cáo khoảng cách kỹ năng và gọi LLM sinh câu giải thích dựa trên evidence của JD và CV.
     */
    public function handle(ScoringService $scoring, LlmProviderInterface $llm): void
    {
        $tracking = JobTracking::find($this->trackingId);
        $report = GapReport::with(['profile.skills', 'jobTarget.skills'])->find($this->reportId);

        if (! $report || ! $report->profile || ! $report->jobTarget) {
            $tracking?->update(['state' => 'failed', 'error' => 'Không tìm thấy dữ liệu báo cáo hoặc hồ sơ.']);

            return;
        }

        try {
            $tracking?->update(['state' => 'active', 'progress' => 25]);

            $cvSkills = $report->profile->skills->map(fn ($s) => [
                'slug' => $s->canonical_slug ?: strtolower($s->raw_name),
                'name' => $s->raw_name,
                'category' => $s->category,
                'level' => $s->level,
                'evidence' => $s->evidence,
            ])->all();

            $jdRequirements = $report->jobTarget->skills->map(fn ($s) => [
                'slug' => $s->canonical_slug ?: strtolower($s->raw_name),
                'name' => $s->raw_name,
                'category' => $s->category,
                'level' => $s->level,
                'importance' => $s->importance,
                'evidence' => $s->evidence,
            ])->all();

            // Lấy bản đồ quan hệ cha-con trong Taxonomy để tính 0.5 điểm khi CV có kỹ năng con
            $taxonomyParentMap = Skill::whereNotNull('parent_slug')
                ->pluck('parent_slug', 'slug')
                ->all();

            $calculated = $scoring->calculate($cvSkills, $jdRequirements, $taxonomyParentMap);
            $tracking?->update(['progress' => 65]);

            // Bổ sung lời giải thích ngắn gọn (1-2 câu) dựa trên bằng chứng nguyên văn cho từng khoảng cách kỹ năng
            $enrichedGaps = [];
            foreach ($calculated['gaps'] as $gap) {
                $prompt = PromptLoader::load('explain-gap', [
                    'skill_name' => (string) $gap['name'],
                    'gap_type' => (string) $gap['type'],
                    'jd_evidence' => (string) $gap['jdEvidence'],
                    'cv_evidence' => (string) ($gap['cvEvidence'] ?? 'Chưa đề cập trong CV'),
                ]);

                try {
                    $explained = $llm->generateObject(
                        jsonSchema: [
                            'type' => 'object',
                            'properties' => [
                                'explanation' => ['type' => 'string'],
                            ],
                            'required' => ['explanation'],
                        ],
                        validator: function (array $data): array {
                            if (empty($data['explanation']) || ! is_string($data['explanation'])) {
                                throw new \InvalidArgumentException('Thiếu trường explanation.');
                            }

                            return $data;
                        },
                        messages: [
                            ['role' => 'user', 'content' => $prompt],
                        ]
                    );
                    $gap['explanation'] = $explained['explanation'];
                } catch (Throwable) {
                    $gap['explanation'] = $gap['type'] === 'missing'
                        ? "Kỹ năng {$gap['name']} được yêu cầu trong JD nhưng chưa có bằng chứng xuất hiện trong CV của bạn."
                        : "Kỹ năng {$gap['name']} trong CV hiện ở mức {$gap['currentLevel']}, cần nâng lên mức {$gap['requiredLevel']} theo yêu cầu của JD.";
                }

                $enrichedGaps[] = $gap;
            }

            $report->update([
                'match_score' => $calculated['matchScore'],
                'category_scores' => $calculated['categoryScores'],
                'matched_skills' => $calculated['matchedSkills'],
                'gaps' => $enrichedGaps,
                'status' => 'ready',
            ]);

            $tracking?->update(['state' => 'completed', 'progress' => 100]);
        } catch (Throwable $e) {
            $report->update(['status' => 'failed']);
            $tracking?->update([
                'state' => 'failed',
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
