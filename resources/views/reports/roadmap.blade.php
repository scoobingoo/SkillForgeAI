@extends('layouts.app')

@section('title', 'Lộ trình Học tập Cá nhân hoá (Hybrid RAG) — SkillForge AI')

@section('content')
@php
    $weeks = is_array($roadmap->weeks) ? $roadmap->weeks : [];
    $totalItems = 0;
    $completedItems = 0;
    $totalHoursAll = 0.0;
    $completedHoursAll = 0.0;

    foreach ($weeks as $wb) {
        foreach ($wb['items'] ?? [] as $it) {
            $totalItems++;
            $h = (float) ($it['estHours'] ?? 2.0);
            $totalHoursAll += $h;
            if (!empty($it['completed'])) {
                $completedItems++;
                $completedHoursAll += $h;
            }
        }
    }
    $initialPct = $totalItems > 0 ? round(($completedItems / $totalItems) * 100) : 0;
@endphp

<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <a href="{{ route('reports.show', $report->id) }}" class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">
                ← Quay lại Báo cáo Skill Gap
            </a>
            <h1 class="text-2xl sm:text-3xl font-extrabold mt-1">Lộ trình Học tập RAG Theo Tuần</h1>
            <p class="text-xs text-slate-500 mt-1">
                Truy xuất bằng Hybrid Search (Vector + Full-Text RRF k=60) từ kho học liệu mở • Giới hạn tối đa <strong>{{ $roadmap->hours_per_week }} giờ/tuần</strong>.
            </p>
        </div>

        <!-- Form điều chỉnh số giờ học mỗi tuần -->
        <form method="GET" action="{{ route('reports.roadmap', $report->id) }}" class="no-print flex items-center gap-2">
            <label class="text-xs font-semibold text-slate-500">Giờ/tuần:</label>
            <input type="number" name="hoursPerWeek" min="2" max="40" value="{{ $roadmap->hours_per_week }}"
                   onclick="this.select()"
                   class="w-16 rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent px-2.5 py-1.5 text-sm font-bold text-center">
            <button type="button" onclick="regenerateRoadmap()"
                    class="px-3.5 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold cursor-pointer">
                Tạo lại Lộ trình (SSE)
            </button>
        </form>
    </div>

    <!-- Thẻ Tiến độ Hoàn thành Tổng thể (Overall Completion Progress Bar) -->
    @if ($totalItems > 0)
        <div class="mb-8 p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">Tiến độ hoàn thành lộ trình</span>
                    <span id="roadmap-progress-badge" class="px-2 py-0.5 rounded-full text-xs font-extrabold bg-indigo-500/10 text-indigo-600 dark:text-indigo-400">
                        {{ $initialPct }}%
                    </span>
                </div>
                <div id="roadmap-progress-stats" class="text-xs text-slate-500 font-medium">
                    Đã hoàn thành <strong>{{ $completedItems }}/{{ $totalItems }}</strong> học liệu ({{ $completedHoursAll }}/{{ $totalHoursAll }} giờ)
                </div>
            </div>
            <div class="w-full h-2.5 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
                <div id="roadmap-progress-bar" class="h-full bg-gradient-to-r from-indigo-600 to-emerald-500 transition-all duration-300 rounded-full" style="width: {{ $initialPct }}%"></div>
            </div>
        </div>
    @endif

    <div id="roadmap-stream-status" class="hidden mb-6 p-4 rounded-xl bg-indigo-500/10 border border-indigo-500/30 text-indigo-600 dark:text-indigo-300 text-xs font-mono"></div>

    <!-- Danh sách lộ trình học tập theo từng tuần -->
    <div class="space-y-6">
        @forelse ($weeks as $weekBlock)
            <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2 pb-4 mb-4 border-b border-slate-100 dark:border-slate-800">
                    <div class="flex items-center gap-3">
                        <span class="px-3 py-1 rounded-xl bg-indigo-600 text-white text-xs font-extrabold">
                            Tuần {{ $weekBlock['week'] }}
                        </span>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($weekBlock['focusSkills'] ?? [] as $fSkill)
                                <span class="text-xs font-mono px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                                    #{{ $fSkill }}
                                </span>
                            @endforeach
                        </div>
                    </div>

                    <span class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                        Tổng thời lượng: {{ $weekBlock['totalHours'] ?? 0 }} / {{ $roadmap->hours_per_week }} giờ
                    </span>
                </div>

                <div class="space-y-3">
                    @foreach ($weekBlock['items'] ?? [] as $item)
                        <div class="flex items-start gap-3.5 p-4 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800">
                            <input type="checkbox"
                                   data-hours="{{ (float) ($item['estHours'] ?? 2) }}"
                                   class="roadmap-item-checkbox mt-1 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer"
                                   {{ !empty($item['completed']) ? 'checked' : '' }}
                                   onchange="toggleRoadmapItem({{ $weekBlock['week'] }}, '{{ $item['resourceSlug'] }}', this.checked)">

                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <a href="{{ $item['url'] ?? '#' }}" target="_blank" rel="noopener noreferrer"
                                       class="font-bold text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
                                        {{ $item['title'] ?? $item['resourceSlug'] }} ↗
                                    </a>
                                    <div class="flex items-center gap-2 text-xs">
                                        <span class="px-2 py-0.5 rounded bg-amber-500/10 text-amber-700 dark:text-amber-300 font-semibold">
                                            {{ $item['estHours'] ?? 2 }} giờ
                                        </span>
                                        <span class="font-mono text-[11px] text-slate-400">{{ $item['resourceSlug'] }}</span>
                                    </div>
                                </div>
                                <p class="text-xs text-slate-600 dark:text-slate-400 mt-1">{{ $item['task'] ?? '' }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-sm text-slate-500">
                Chưa có mục học tập nào trong lộ trình. Hãy chạy <code>php artisan app:ingest</code> để nạp kho học liệu RAG.
            </div>
        @endforelse
    </div>
</div>

@push('scripts')
<script>
    function recalculateRoadmapProgress() {
        const checkboxes = document.querySelectorAll('.roadmap-item-checkbox');
        if (!checkboxes.length) return;

        let total = checkboxes.length;
        let done = 0;
        let totalHours = 0;
        let doneHours = 0;

        checkboxes.forEach(cb => {
            const h = parseFloat(cb.getAttribute('data-hours') || '2');
            totalHours += h;
            if (cb.checked) {
                done++;
                doneHours += h;
            }
        });

        const pct = Math.round((done / total) * 100);
        const bar = document.getElementById('roadmap-progress-bar');
        const badge = document.getElementById('roadmap-progress-badge');
        const stats = document.getElementById('roadmap-progress-stats');

        if (bar) bar.style.width = `${pct}%`;
        if (badge) badge.textContent = `${pct}%`;
        if (stats) stats.innerHTML = `Đã hoàn thành <strong>${done}/${total}</strong> học liệu (${doneHours}/${totalHours} giờ)`;
    }

    async function toggleRoadmapItem(week, resourceSlug, completed) {
        recalculateRoadmapProgress();
        await fetch('/api/v1/roadmaps/{{ $roadmap->id }}/progress', {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ week, resourceSlug, completed })
        });
    }

    async function regenerateRoadmap() {
        const hoursInput = document.querySelector('input[name="hoursPerWeek"]');
        const hoursPerWeek = parseInt(hoursInput?.value || '10', 10);
        const statusBox = document.getElementById('roadmap-stream-status');
        statusBox.classList.remove('hidden');
        statusBox.textContent = 'Đang kết nối luồng SSE (/api/v1/reports/{{ $report->id }}/roadmap)...';

        const res = await fetch('/api/v1/reports/{{ $report->id }}/roadmap', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'text/event-stream'
            },
            body: JSON.stringify({ hoursPerWeek, weeks: 4, stream: true })
        });

        const text = await res.text();
        if (text.includes('event: done')) {
            window.location.href = `?hoursPerWeek=${hoursPerWeek}`;
        }
    }
</script>
@endpush
@endsection
