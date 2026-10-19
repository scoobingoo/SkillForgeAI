<?php

// SPDX-License-Identifier: Apache-2.0
// Controller phục vụ toàn bộ các trang giao diện Web HTML (Blade + Tailwind CSS) của hệ thống SkillForge AI

namespace App\Http\Controllers;

use App\Models\GapReport;
use App\Models\InterviewSession;
use App\Models\Profile;
use App\Models\Resource;
use App\Models\Skill;
use App\Services\RagRetrievalService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\Yaml\Yaml;
use Throwable;

class WebController extends Controller
{
    /**
     * Route `/`: Trang chủ (Landing Page) kèm thống kê hệ thống, nút "Bắt đầu" và "Dùng CV mẫu".
     */
    public function landing(): View
    {
        try {
            $recentReports = GapReport::with(['profile', 'jobTarget'])
                ->where('status', 'ready')
                ->latest()
                ->take(3)
                ->get();

            $stats = [
                'taxonomySkills' => Skill::count() ?: 80,
                'ragResources' => Resource::count() ?: 30,
                'analyzedReports' => GapReport::where('status', 'ready')->count(),
            ];
        } catch (Throwable) {
            $recentReports = collect();
            $stats = [
                'taxonomySkills' => 80,
                'ragResources' => 30,
                'analyzedReports' => 0,
            ];
        }

        return view('landing', [
            'recentReports' => $recentReports,
            'stats' => $stats,
        ]);
    }

    /**
     * Route `/onboarding`: Trang tải lên PDF CV, chọn vị trí mẫu hoặc dán JD tự do.
     */
    public function onboarding(Request $request): View
    {
        $roles = [];
        foreach (glob(base_path('data/roles/*.yaml')) ?: [] as $file) {
            $parsed = Yaml::parseFile($file);
            if (is_array($parsed) && isset($parsed['slug'])) {
                $roles[] = $parsed;
            }
        }

        $sampleCvs = [
            'fresher-php' => 'Nguyễn Minh Khôi — Sinh viên năm 4 / Fresher PHP & Laravel',
            'frontend-student' => 'Trần Thị Mai Anh — Sinh viên năm 3 / Frontend Web Developer',
            'data-student' => 'Lê Hoàng Nam — Thực tập sinh Phân tích Dữ liệu (Data Analyst)',
        ];

        $sampleJds = [
            'jd-laravel-fullstack' => [
                'title' => 'JD Mẫu: Kỹ sư Fullstack / Backend Laravel',
                'content' => is_file(base_path('data/samples/jd-laravel-fullstack.txt'))
                    ? (string) file_get_contents(base_path('data/samples/jd-laravel-fullstack.txt'))
                    : '',
            ],
            'jd-frontend-engineer' => [
                'title' => 'JD Mẫu: Kỹ sư Frontend Web (React / TypeScript / Tailwind)',
                'content' => is_file(base_path('data/samples/jd-frontend-engineer.txt'))
                    ? (string) file_get_contents(base_path('data/samples/jd-frontend-engineer.txt'))
                    : '',
            ],
            'jd-data-analyst' => [
                'title' => 'JD Mẫu: Chuyên viên Phân tích Dữ liệu (SQL / Python / BI)',
                'content' => is_file(base_path('data/samples/jd-data-analyst.txt'))
                    ? (string) file_get_contents(base_path('data/samples/jd-data-analyst.txt'))
                    : '',
            ],
        ];

        return view('onboarding', [
            'roles' => $roles,
            'sampleCvs' => $sampleCvs,
            'sampleJds' => $sampleJds,
            'preselectedSample' => $request->query('sample'),
        ]);
    }

    /**
     * Route `/profiles/{id}`: Hiển thị kỹ năng trích xuất được từ CV kèm trích dẫn, cho phép chỉnh sửa tay.
     */
    public function showProfile(string $id): View
    {
        $profile = Profile::with(['skills', 'gapReports.jobTarget'])->findOrFail($id);
        $taxonomySkills = Skill::orderBy('category')->orderBy('name')->get();

        $roles = [];
        foreach (glob(base_path('data/roles/*.yaml')) ?: [] as $file) {
            $parsed = Yaml::parseFile($file);
            if (is_array($parsed) && isset($parsed['slug'])) {
                $roles[] = $parsed;
            }
        }

        return view('profiles.show', [
            'profile' => $profile,
            'taxonomySkills' => $taxonomySkills,
            'roles' => $roles,
        ]);
    }

    /**
     * Route `/reports/{id}`: Hiển thị % đáp ứng, biểu đồ Radar theo nhóm kỹ năng, danh sách Gap và Popover trích dẫn.
     */
    public function showReport(string $id): View
    {
        $report = GapReport::with(['profile.skills', 'jobTarget.skills', 'roadmap', 'interviewSessions'])
            ->findOrFail($id);

        return view('reports.show', [
            'report' => $report,
        ]);
    }

    /**
     * Route `/reports/{id}/roadmap`: Hiển thị lộ trình học tập theo tuần, checkbox tiến độ và link nguồn tài liệu mở.
     */
    public function showRoadmap(Request $request, string $id, RagRetrievalService $rag): View
    {
        $report = GapReport::with(['profile', 'jobTarget', 'roadmap'])->findOrFail($id);
        $hoursPerWeek = (int) $request->query('hoursPerWeek', $request->session()->get('hours_per_week', 10));

        $roadmap = $report->roadmap;
        if (! $roadmap) {
            $roadmap = $rag->generateRoadmap($report, $hoursPerWeek, 4);
        }

        return view('reports.roadmap', [
            'report' => $report,
            'roadmap' => $roadmap,
        ]);
    }

    /**
     * Route `/interview/{sessionId}`: Giao diện chat phỏng vấn thử thích ứng, nhận xét Rubric và tổng kết.
     */
    public function showInterview(string $sessionId): View
    {
        $session = InterviewSession::with(['turns', 'gapReport.profile', 'gapReport.jobTarget'])
            ->findOrFail($sessionId);

        return view('interview.show', [
            'session' => $session,
        ]);
    }

    /**
     * Route `/settings`: Cài đặt Model, ngôn ngữ (VI/EN), số giờ học mỗi tuần và xoá toàn bộ dữ liệu cá nhân.
     */
    public function settings(Request $request): View
    {
        $profiles = Profile::withCount('skills')->latest()->get();

        return view('settings', [
            'profiles' => $profiles,
            'currentLocale' => app()->getLocale(),
            'hoursPerWeek' => (int) $request->session()->get('hours_per_week', 10),
            'chatModel' => config('ai.model'),
            'embedModel' => config('ai.embed_model'),
        ]);
    }

    /**
     * POST `/settings`: Lưu tuỳ chọn ngôn ngữ và số giờ học mỗi tuần vào session/cookie.
     */
    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'in:vi,en'],
            'hours_per_week' => ['required', 'integer', 'min:2', 'max:60'],
        ]);

        $request->session()->put('locale', $validated['locale']);
        $request->session()->put('hours_per_week', (int) $validated['hours_per_week']);

        return redirect()->route('settings')
            ->withCookie(cookie('acc_locale', $validated['locale'], 60 * 24 * 30))
            ->with('status', __('Đã lưu cài đặt thành công!'));
    }

    /**
     * DELETE `/settings/purge`: Xoá toàn bộ hồ sơ CV và dữ liệu liên quan (cascade).
     */
    public function purgeAllData(): RedirectResponse
    {
        Profile::query()->delete();

        return redirect()->route('settings')
            ->with('status', __('Đã xoá toàn bộ hồ sơ CV và dữ liệu liên quan.'));
    }

    /**
     * Route `/api/docs`: Hiển thị trang tài liệu OpenAPI 3.0 (Swagger UI).
     */
    public function apiDocs(): View
    {
        return view('swagger');
    }

    /**
     * Route `/api/openapi.json`: Đặc tả JSON OpenAPI 3.0 cho toàn bộ các endpoint /api/v1/*.
     */
    public function openApiSpec(): JsonResponse
    {
        return response()->json([
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'SkillForge AI OpenAPI',
                'version' => '1.0.0',
                'description' => 'Đặc tả REST API chính thức của nền tảng mã nguồn mở SkillForge AI (Apache-2.0).',
                'license' => [
                    'name' => 'Apache-2.0',
                    'url' => 'https://www.apache.org/licenses/LICENSE-2.0',
                ],
            ],
            'servers' => [
                ['url' => '/api/v1', 'description' => 'API v1 Server'],
            ],
            'paths' => [
                '/health' => [
                    'get' => [
                        'summary' => 'Kiểm tra trạng thái DB, Ollama và model AI đã tải xong chưa',
                        'responses' => ['200' => ['description' => 'Hệ thống sẵn sàng'], '503' => ['description' => 'Đang tải model hoặc dịch vụ chưa sẵn sàng']],
                    ],
                ],
                '/profiles' => [
                    'post' => [
                        'summary' => 'Tải lên CV PDF hoặc chọn CV mẫu (trả về 202 kèm profileId, jobId)',
                        'responses' => ['202' => ['description' => 'Đã tiếp nhận xử lý']],
                    ],
                ],
                '/profiles/{id}' => [
                    'get' => ['summary' => 'Xem chi tiết hồ sơ và kỹ năng trích xuất', 'responses' => ['200' => ['description' => 'Profile']]],
                    'delete' => ['summary' => 'Xoá hồ sơ và cascade toàn bộ dữ liệu liên quan', 'responses' => ['204' => ['description' => 'Đã xoá']]],
                ],
                '/profiles/{id}/skills' => [
                    'patch' => ['summary' => 'Chỉnh sửa danh sách kỹ năng thủ công', 'responses' => ['200' => ['description' => 'Profile đã cập nhật']]],
                ],
                '/roles' => [
                    'get' => ['summary' => 'Lấy danh sách 5 vị trí công việc mẫu', 'responses' => ['200' => ['description' => 'Role[]']]],
                ],
                '/job-targets' => [
                    'post' => ['summary' => 'Tạo mục tiêu công việc từ roleSlug hoặc jdText', 'responses' => ['202' => ['description' => 'Đã tiếp nhận xử lý']]],
                ],
                '/reports' => [
                    'post' => ['summary' => 'Tạo báo cáo phân tích khoảng cách kỹ năng (Skill Gap)', 'responses' => ['202' => ['description' => 'Đã tiếp nhận xử lý']]],
                ],
                '/reports/{id}' => [
                    'get' => ['summary' => 'Lấy kết quả báo cáo Skill Gap kèm trích dẫn bằng chứng', 'responses' => ['200' => ['description' => 'GapReport']]],
                ],
                '/reports/{id}/roadmap' => [
                    'post' => ['summary' => 'Sinh lộ trình học tập RAG theo tuần (hỗ trợ SSE)', 'responses' => ['200' => ['description' => 'Roadmap']]],
                ],
                '/roadmaps/{id}/progress' => [
                    'patch' => ['summary' => 'Cập nhật tiến độ hoàn thành mục học tập', 'responses' => ['200' => ['description' => 'Roadmap']]],
                ],
                '/interviews' => [
                    'post' => ['summary' => 'Tạo phiên phỏng vấn thử thích ứng (5 câu mặc định)', 'responses' => ['201' => ['description' => 'InterviewSession']]],
                ],
                '/interviews/{id}' => [
                    'get' => ['summary' => 'Lấy thông tin phiên phỏng vấn thử và điểm trung bình', 'responses' => ['200' => ['description' => 'InterviewSession']]],
                ],
                '/interviews/{id}/answers' => [
                    'post' => ['summary' => 'Gửi câu trả lời phỏng vấn, chấm điểm Rubric và nhận câu hỏi tiếp theo (SSE)', 'responses' => ['200' => ['description' => 'Grade + Next Turn']]],
                ],
                '/jobs/{id}' => [
                    'get' => ['summary' => 'Tra cứu trạng thái và % tiến độ của job bất đồng bộ', 'responses' => ['200' => ['description' => 'Job status']]],
                ],
            ],
        ]);
    }
}
