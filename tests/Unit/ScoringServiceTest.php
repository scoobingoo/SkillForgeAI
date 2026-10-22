<?php

// SPDX-License-Identifier: Apache-2.0
// Unit Test toàn diện cho dịch vụ tính điểm thuần tuý ScoringService

namespace Tests\Unit;

use App\Services\ScoringService;
use PHPUnit\Framework\TestCase;

class ScoringServiceTest extends TestCase
{
    private ScoringService $scoring;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scoring = new ScoringService;
    }

    /**
     * Kiểm tra quy đổi mức độ kỹ năng sang số nguyên (beginner=1, intermediate=2, advanced=3, mặc định=2).
     */
    public function test_level_to_number_and_importance_weight(): void
    {
        $this->assertSame(1, ScoringService::levelToNumber('beginner'));
        $this->assertSame(2, ScoringService::levelToNumber('intermediate'));
        $this->assertSame(3, ScoringService::levelToNumber('advanced'));
        $this->assertSame(2, ScoringService::levelToNumber(null));

        $this->assertSame(2, ScoringService::importanceWeight('required'));
        $this->assertSame(1, ScoringService::importanceWeight('nice_to_have'));
    }

    /**
     * Kiểm tra trường hợp JD rỗng trả về matchScore = 0.0.
     */
    public function test_empty_jd_returns_zero_score(): void
    {
        $result = $this->scoring->calculate([], []);
        $this->assertSame(0.0, $result['matchScore']);
        $this->assertEmpty($result['gaps']);
        $this->assertEmpty($result['matchedSkills']);
    }

    /**
     * Kiểm tra công thức tính điểm chuẩn:
     * - Kỹ năng khớp đủ mức độ: min(1, levelCv / levelJd) = 1.0
     * - Kỹ năng thấp hơn mức yêu cầu (beginner=1 so với advanced=3): 1/3
     * - Kỹ năng chỉ có kỹ năng con trong CV (parent match): 0.5
     * - Kỹ năng không có trong CV: 0.0
     */
    public function test_scoring_formula_with_weights_and_parent_taxonomy(): void
    {
        $cvSkills = [
            // 1. PHP đạt advanced (3) >= JD intermediate (2) -> điểm = 1.0
            ['slug' => 'php', 'name' => 'PHP', 'category' => 'backend', 'level' => 'advanced', 'evidence' => '3 năm PHP'],
            // 2. Laravel đạt beginner (1) < JD advanced (3) -> điểm = 1/3
            ['slug' => 'laravel', 'name' => 'Laravel', 'category' => 'backend', 'level' => 'beginner', 'evidence' => 'Biết cơ bản Laravel'],
            // 3. CV chỉ có tailwind-css (con của css3), trong khi JD yêu cầu css3 -> điểm = 0.5
            ['slug' => 'tailwind-css', 'name' => 'Tailwind CSS', 'category' => 'frontend', 'level' => 'intermediate', 'evidence' => 'Dùng Tailwind'],
        ];

        $jdRequirements = [
            ['slug' => 'php', 'name' => 'PHP', 'category' => 'backend', 'level' => 'intermediate', 'importance' => 'required', 'evidence' => 'Cần PHP'],
            ['slug' => 'laravel', 'name' => 'Laravel', 'category' => 'backend', 'level' => 'advanced', 'importance' => 'required', 'evidence' => 'Cần Laravel nâng cao'],
            ['slug' => 'css3', 'name' => 'CSS3', 'category' => 'frontend', 'level' => 'intermediate', 'importance' => 'nice_to_have', 'evidence' => 'Ưu tiên CSS3'],
            ['slug' => 'docker', 'name' => 'Docker', 'category' => 'devops', 'level' => 'intermediate', 'importance' => 'required', 'evidence' => 'Bắt buộc Docker'],
        ];

        $parentMap = [
            'tailwind-css' => 'css3',
        ];

        $res = $this->scoring->calculate($cvSkills, $jdRequirements, $parentMap);

        // Tổng trọng số: 2 (php) + 2 (laravel) + 1 (css3) + 2 (docker) = 7
        // Tổng điểm có trọng số: 2*(1.0) + 2*(1/3) + 1*(0.5) + 2*(0.0) = 3.166666...
        // matchScore = 3.166666... / 7 = 0.4524
        $this->assertEqualsWithDelta(0.4524, $res['matchScore'], 0.001);
        $this->assertCount(1, $res['matchedSkills']);
        $this->assertSame('php', $res['matchedSkills'][0]['skill']);

        // Có 3 gaps: docker (missing, required), laravel (below_level, required), css3 (below_level via child, nice_to_have)
        $this->assertCount(3, $res['gaps']);
        $this->assertSame('docker', $res['gaps'][0]['skill']);
        $this->assertSame('missing', $res['gaps'][0]['type']);
        $this->assertSame('laravel', $res['gaps'][1]['skill']);
        $this->assertSame('below_level', $res['gaps'][1]['type']);
        $this->assertTrue($res['gaps'][2]['matchedViaChild']);
    }
}
