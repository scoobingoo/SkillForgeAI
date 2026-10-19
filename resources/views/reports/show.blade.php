@extends('layouts.app')

@section('title', 'Báo cáo Skill Gap — ' . ($report->jobTarget?->title ?? 'SkillForge AI'))

@section('content')
@php
    $matchPercent = round(((float) $report->match_score) * 100, 1);
    $gaps = is_array($report->gaps) ? $report->gaps : [];
    $matchedSkills = is_array($report->matched_skills) ? $report->matched_skills : [];
    $categoryScores = is_array($report->category_scores) ? $report->category_scores : [];
@endphp

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <!-- Đầu trang Báo cáo -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 mb-8">
        <div>
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <a href="{{ route('profiles.show', $report->profile_id) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline font-semibold">
                    {{ $report->profile?->title ?? 'Hồ sơ CV' }}
                </a>
                <span class="text-slate-400">→</span>
                <span class="font-semibold text-slate-600 dark:text-slate-300">{{ $report->jobTarget?->title ?? 'Vị trí mục tiêu' }}</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold mt-1">Báo cáo Phân tích Khoảng cách Kỹ năng (Skill Gap)</h1>
        </div>

        <!-- Các nút hành động: Chỉnh sửa CV, Xuất JSON, In PDF, Lộ trình học RAG, Phỏng vấn thử -->
        <div class="no-print flex flex-wrap items-center gap-2.5">
            <a href="{{ route('profiles.show', $report->profile_id) }}"
               class="px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 text-xs font-semibold hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                Sửa kỹ năng CV
            </a>

            <button type="button" onclick="exportReportJson()"
                    class="px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 text-xs font-semibold hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer">
                Xuất JSON
            </button>

            <button type="button" onclick="window.print()"
                    class="px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 text-xs font-semibold hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer">
                In / Lưu PDF
            </button>

            <a href="{{ route('reports.roadmap', $report->id) }}"
               class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs sm:text-sm font-bold shadow-sm transition">
                Xem Lộ trình Học RAG →
            </a>

            <button type="button" id="start-interview-btn" onclick="startMockInterview()"
                    class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs sm:text-sm font-bold shadow-sm transition cursor-pointer">
                Luyện Phỏng vấn Thử (5 câu)
            </button>
        </div>
    </div>

    <!-- Khối Điểm Tương thích (%) và Biểu đồ Radar theo nhóm kỹ năng -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-10">
        <!-- Cột Điểm % Đáp ứng -->
        <div class="lg:col-span-5 p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col justify-between">
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Độ tương thích tổng thể (Match Score)</span>
                <div class="mt-4 flex items-baseline gap-3">
                    <span class="text-5xl sm:text-6xl font-extrabold text-indigo-600 dark:text-indigo-400">{{ $matchPercent }}%</span>
                    <span class="text-xs text-slate-500">trên thang 100% (Required ×2, Nice-to-have ×1)</span>
                </div>

                <div class="mt-4 w-full h-3 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
                    <div class="h-full bg-indigo-600 rounded-full" style="width: {{ min(100, $matchPercent) }}%"></div>
                </div>
            </div>

            <div class="mt-6 grid grid-cols-2 gap-4 pt-6 border-t border-slate-100 dark:border-slate-800">
                <div class="p-3.5 rounded-xl bg-emerald-500/10 border border-emerald-500/20">
                    <div class="text-2xl font-extrabold text-emerald-600 dark:text-emerald-400">{{ count($matchedSkills) }}</div>
                    <div class="text-xs text-slate-600 dark:text-slate-300 font-medium">Kỹ năng đã đáp ứng đủ</div>
                </div>
                <div class="p-3.5 rounded-xl bg-amber-500/10 border border-amber-500/20">
                    <div class="text-2xl font-extrabold text-amber-600 dark:text-amber-400">{{ count($gaps) }}</div>
                    <div class="text-xs text-slate-600 dark:text-slate-300 font-medium">Khoảng cách kỹ năng (Gaps)</div>
                </div>
            </div>
        </div>

        <!-- Cột Radar Chart theo nhóm kỹ năng -->
        <div class="lg:col-span-7 p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-bold text-base">Biểu đồ Radar theo Nhóm Kỹ năng</h2>
                <span class="text-xs text-slate-500">Tỷ lệ đáp ứng từng mảng (%)</span>
            </div>
            <div class="h-64 sm:h-72 flex items-center justify-center">
                <canvas id="skillRadarChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Danh sách Khoảng cách Kỹ năng (Gaps) kèm thanh lọc và nút xem Popover Trích dẫn nguyên văn -->
    <div class="mb-10">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
            <div>
                <h2 class="text-xl font-extrabold">Chi tiết Khoảng cách Kỹ năng ({{ count($gaps) }} Gaps)</h2>
                <p class="text-xs text-slate-500 mt-0.5">Bấm vào <strong>"Xem trích dẫn nguyên văn"</strong> trên từng kỹ năng để đối chiếu bằng chứng (<code>evidence</code>) từ JD và CV.</p>
            </div>

            <!-- Bộ lọc nhanh khoảng cách kỹ năng (Interactive Filter Tabs) -->
            @if (! empty($gaps))
                <div class="no-print flex flex-wrap items-center gap-1.5 text-xs">
                    <button type="button" onclick="filterGaps('all', this)"
                            class="gap-filter-btn px-3 py-1.5 rounded-lg font-semibold bg-indigo-600 text-white cursor-pointer">
                        Tất cả ({{ count($gaps) }})
                    </button>
                    <button type="button" onclick="filterGaps('required', this)"
                            class="gap-filter-btn px-3 py-1.5 rounded-lg font-semibold bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-300 cursor-pointer">
                        Bắt buộc (×2)
                    </button>
                    <button type="button" onclick="filterGaps('missing', this)"
                            class="gap-filter-btn px-3 py-1.5 rounded-lg font-semibold bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-300 cursor-pointer">
                        Thiếu hoàn toàn
                    </button>
                    <button type="button" onclick="filterGaps('below_level', this)"
                            class="gap-filter-btn px-3 py-1.5 rounded-lg font-semibold bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-300 cursor-pointer">
                        Chưa đủ mức
                    </button>
                </div>
            @endif
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @forelse ($gaps as $idx => $gap)
                <div class="gap-card p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col justify-between"
                     data-priority="{{ $gap['priority'] ?? 'required' }}"
                     data-type="{{ $gap['type'] ?? 'missing' }}">
                    <div>
                        <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-base">{{ $gap['name'] ?? $gap['skill'] }}</span>
                                <span class="text-[11px] font-mono px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-500">{{ $gap['category'] ?? 'general' }}</span>
                            </div>

                            <div class="flex items-center gap-1.5">
                                @if (($gap['priority'] ?? 'required') === 'required')
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20">
                                        Bắt buộc (×2)
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-slate-500/10 text-slate-600 dark:text-slate-300">
                                        Ưu tiên (×1)
                                    </span>
                                @endif

                                @if (($gap['type'] ?? 'missing') === 'missing')
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-amber-500/15 text-amber-700 dark:text-amber-300">
                                        Thiếu hoàn toàn
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-sky-500/15 text-sky-700 dark:text-sky-300">
                                        Chưa đủ mức ({{ $gap['currentLevel'] ?? 'beginner' }} → {{ $gap['requiredLevel'] ?? 'intermediate' }})
                                    </span>
                                @endif
                            </div>
                        </div>

                        <p class="text-sm text-slate-600 dark:text-slate-300 mt-2">
                            {{ $gap['explanation'] ?? '' }}
                        </p>
                    </div>

                    <!-- Nút mở Popover/Chi tiết Trích dẫn nguyên văn từ JD & CV -->
                    <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800">
                        <button type="button" onclick="toggleEvidence('evidence-box-{{ $idx }}')"
                                class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1 cursor-pointer">
                            <span>🔍 Xem trích dẫn nguyên văn (Evidence)</span>
                        </button>

                        <div id="evidence-box-{{ $idx }}" class="hidden mt-3 p-3.5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 space-y-2 text-xs">
                            <div>
                                <span class="font-bold text-rose-600 dark:text-rose-400">Trích dẫn từ JD:</span>
                                <blockquote class="mt-0.5 italic text-slate-700 dark:text-slate-300 border-l-2 border-rose-500 pl-2.5">
                                    "{{ $gap['jdEvidence'] ?? 'Không có trích dẫn' }}"
                                </blockquote>
                            </div>
                            <div>
                                <span class="font-bold text-emerald-600 dark:text-emerald-400">Trích dẫn từ CV:</span>
                                <blockquote class="mt-0.5 italic text-slate-700 dark:text-slate-300 border-l-2 border-emerald-500 pl-2.5">
                                    "{{ $gap['cvEvidence'] ?? 'Chưa xuất hiện trong văn bản CV gốc.' }}"
                                </blockquote>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-2 p-6 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-700 dark:text-emerald-300 text-sm font-medium">
                    Chúc mừng! Hồ sơ CV của bạn đã đáp ứng 100% các kỹ năng yêu cầu của vị trí này.
                </div>
            @endforelse
        </div>
    </div>

    <!-- Danh sách Kỹ năng đã đáp ứng (Matched Skills) -->
    @if (! empty($matchedSkills))
        <div>
            <h2 class="text-lg font-bold mb-4">Kỹ năng Đã Đáp ứng Đầy đủ ({{ count($matchedSkills) }})</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach ($matchedSkills as $m)
                    <div class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-emerald-500/30 text-xs space-y-1.5">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-sm text-emerald-600 dark:text-emerald-400">✓ {{ $m['name'] ?? $m['skill'] }}</span>
                            <span class="font-mono px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-600">100%</span>
                        </div>
                        <div class="text-slate-500 truncate" title="{{ $m['cvEvidence'] ?? '' }}">
                            <strong>CV:</strong> "{{ $m['cvEvidence'] ?? '' }}"
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>

@push('scripts')
<script>
    const reportExportPayload = @json($report);

    function toggleEvidence(id) {
        document.getElementById(id)?.classList.toggle('hidden');
    }

    function filterGaps(mode, clickedBtn) {
        document.querySelectorAll('.gap-filter-btn').forEach(btn => {
            btn.className = 'gap-filter-btn px-3 py-1.5 rounded-lg font-semibold bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-300 cursor-pointer';
        });
        if (clickedBtn) {
            clickedBtn.className = 'gap-filter-btn px-3 py-1.5 rounded-lg font-semibold bg-indigo-600 text-white cursor-pointer';
        }

        document.querySelectorAll('.gap-card').forEach(card => {
            const priority = card.getAttribute('data-priority');
            const type = card.getAttribute('data-type');
            let show = true;
            if (mode === 'required') show = (priority === 'required');
            else if (mode === 'missing') show = (type === 'missing');
            else if (mode === 'below_level') show = (type === 'below_level');
            card.classList.toggle('hidden', !show);
        });
    }

    function exportReportJson() {
        const blob = new Blob([JSON.stringify(reportExportPayload, null, 2)], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `skillforge-gap-report-{{ $report->id }}.json`;
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);
    }

    // Khởi tạo biểu đồ Radar Chart bằng Chart.js
    document.addEventListener('DOMContentLoaded', function () {
        const rawScores = @json($categoryScores);
        const labels = Object.keys(rawScores).length ? Object.keys(rawScores).map(k => k.toUpperCase()) : ['FRONTEND', 'BACKEND', 'DATA', 'DEVOPS', 'TESTING'];
        const values = Object.keys(rawScores).length ? Object.values(rawScores).map(v => Math.round(v * 100)) : [80, 65, 50, 40, 60];

        const ctx = document.getElementById('skillRadarChart');
        if (ctx && window.Chart) {
            new Chart(ctx, {
                type: 'radar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: '% Đáp ứng theo nhóm kỹ năng',
                        data: values,
                        fill: true,
                        backgroundColor: 'rgba(79, 70, 229, 0.2)',
                        borderColor: 'rgb(79, 70, 229)',
                        pointBackgroundColor: 'rgb(79, 70, 229)',
                        pointBorderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        r: {
                            suggestedMin: 0,
                            suggestedMax: 100,
                            ticks: { stepSize: 25 }
                        }
                    }
                }
            });
        }
    });

    async function startMockInterview() {
        const btn = document.getElementById('start-interview-btn');
        btn.disabled = true;
        btn.textContent = 'Đang khởi tạo câu hỏi phỏng vấn...';
        try {
            const res = await fetch('/api/v1/interviews', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({
                    reportId: '{{ $report->id }}',
                    questionCount: 5,
                    types: ['concept', 'scenario', 'behavioral', 'coding_discussion']
                })
            });
            const data = await res.json();
            if (res.ok && data.sessionId) {
                window.location.href = `/interview/${data.sessionId}`;
            } else {
                alert(data?.error?.message || 'Không thể khởi tạo phiên phỏng vấn.');
                btn.disabled = false;
                btn.textContent = 'Luyện Phỏng vấn Thử (5 câu)';
            }
        } catch (e) {
            btn.disabled = false;
            btn.textContent = 'Luyện Phỏng vấn Thử (5 câu)';
        }
    }
</script>
@endpush
@endsection
