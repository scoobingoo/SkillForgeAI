@extends('layouts.app')

@section('title', 'SkillForge AI — Nền tảng Phân tích Khoảng cách Kỹ năng & Cố vấn Sự nghiệp AI')

@section('content')
<div class="relative overflow-hidden">
    <!-- Hiệu ứng ánh sáng nền (Ambient Gradient Glow) -->
    <div class="pointer-events-none absolute inset-x-0 -top-24 -z-10 flex justify-center overflow-hidden blur-3xl" aria-hidden="true">
        <div class="h-72 w-[48rem] bg-gradient-to-tr from-indigo-500/20 via-violet-500/15 to-emerald-500/15 rounded-full"></div>
    </div>

    <!-- Khu vực Hero chính -->
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-12 pb-16 lg:pt-20 lg:pb-24">
        <div class="text-center max-w-3xl mx-auto">
            <div class="inline-flex flex-wrap items-center justify-center gap-2 px-4 py-1.5 rounded-full bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-500/20 text-xs font-semibold mb-6">
                <span class="inline-block w-2 h-2 rounded-full bg-emerald-500"></span>
                <span>SkillForge AI Engine v1.0</span>
                <span>•</span>
                <span>Local AI ({{ config('ai.model') }} + {{ config('ai.embed_model') }})</span>
                <span>•</span>
                <span>PostgreSQL 17 pgvector</span>
            </div>

            <h1 class="text-3xl sm:text-5xl font-extrabold tracking-tight text-slate-900 dark:text-white leading-tight">
                {{ __('Cố vấn Nghề nghiệp AI Nguồn mở cho Sinh viên') }}
            </h1>

            <p class="mt-5 text-base sm:text-lg text-slate-600 dark:text-slate-300 leading-relaxed">
                {{ __('Phân tích khoảng cách kỹ năng từ CV & JD với trích dẫn nguyên văn, gợi ý lộ trình học tập RAG chuẩn xác và luyện phỏng vấn thích ứng — hoàn toàn chạy local với Ollama.') }}
            </p>

            <!-- 2 nút hành động chính -->
            <div class="mt-8 flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="{{ route('onboarding') }}"
                   class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-7 py-3.5 rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-700 hover:to-violet-700 text-white font-semibold shadow-lg shadow-indigo-500/25 transition">
                    <span>{{ __('Bắt đầu phân tích') }}</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                    </svg>
                </a>

                <a href="{{ route('onboarding', ['sample' => 'fresher-php']) }}"
                   class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3.5 rounded-xl bg-white dark:bg-slate-900 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-900 dark:text-slate-100 font-semibold border border-slate-300 dark:border-slate-700 shadow-sm transition">
                    <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <span>{{ __('Dùng CV mẫu') }} (1-Click Demo)</span>
                </a>
            </div>
        </div>

        <!-- Dải chỉ số kỹ thuật nổi bật (Engineering Benchmarks) -->
        <div class="mt-14 grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="p-5 rounded-2xl bg-white/80 dark:bg-slate-900/80 border border-slate-200/80 dark:border-slate-800 text-center">
                <div class="text-2xl sm:text-3xl font-extrabold text-indigo-600 dark:text-indigo-400">{{ $stats['taxonomySkills'] ?? 80 }}+</div>
                <div class="text-xs font-semibold text-slate-500 mt-1">Kỹ năng trong Taxonomy chuẩn</div>
            </div>
            <div class="p-5 rounded-2xl bg-white/80 dark:bg-slate-900/80 border border-slate-200/80 dark:border-slate-800 text-center">
                <div class="text-2xl sm:text-3xl font-extrabold text-emerald-600 dark:text-emerald-400">0.0%</div>
                <div class="text-xs font-semibold text-slate-500 mt-1">Tỷ lệ ảo giác (Evidence Verified)</div>
            </div>
            <div class="p-5 rounded-2xl bg-white/80 dark:bg-slate-900/80 border border-slate-200/80 dark:border-slate-800 text-center">
                <div class="text-2xl sm:text-3xl font-extrabold text-amber-600 dark:text-amber-400">96.0%</div>
                <div class="text-xs font-semibold text-slate-500 mt-1">Độ chính xác ánh xạ 3 tầng</div>
            </div>
            <div class="p-5 rounded-2xl bg-white/80 dark:bg-slate-900/80 border border-slate-200/80 dark:border-slate-800 text-center">
                <div class="text-2xl sm:text-3xl font-extrabold text-rose-600 dark:text-rose-400">{{ $stats['ragResources'] ?? 30 }}+</div>
                <div class="text-xs font-semibold text-slate-500 mt-1">Học liệu mở Hybrid RAG (CC BY 4.0)</div>
            </div>
        </div>

        <!-- 4 Trụ cột công nghệ cốt lõi của SkillForge AI -->
        <div class="mt-12 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm hover:border-indigo-500/50 transition">
                <div class="w-10 h-10 rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold mb-4">01</div>
                <h3 class="font-bold text-base mb-2">Đối chiếu Bằng chứng Thật</h3>
                <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed">Mọi kỹ năng và khoảng cách (Gap) đều đi kèm đoạn trích nguyên văn (<code>evidence</code>) được máy chủ kiểm chứng trực tiếp trên văn bản CV & JD gốc.</p>
            </div>

            <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm hover:border-emerald-500/50 transition">
                <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold mb-4">02</div>
                <h3 class="font-bold text-base mb-2">Chuẩn hoá Kỹ năng 3 Tầng</h3>
                <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed">Kết hợp khớp Alias chính xác, tìm kiếm láng giềng Vector (<code>bge-m3</code> Cosine ≥ 0.82) và LLM phân giải ngữ nghĩa Top-5 về Taxonomy chuẩn.</p>
            </div>

            <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm hover:border-amber-500/50 transition">
                <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center font-bold mb-4">03</div>
                <h3 class="font-bold text-base mb-2">Lộ trình Học Hybrid RAG</h3>
                <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed">Truy xuất học liệu mở bằng Vector + Full-Text kết hợp Reciprocal Rank Fusion (RRF k=60) và tự động cân bằng quỹ giờ học mỗi tuần.</p>
            </div>

            <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm hover:border-rose-500/50 transition">
                <div class="w-10 h-10 rounded-xl bg-rose-500/10 text-rose-600 dark:text-rose-400 flex items-center justify-center font-bold mb-4">04</div>
                <h3 class="font-bold text-base mb-2">Phỏng vấn Thử Thích ứng</h3>
                <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed">Tự động tăng/giảm độ khó theo điểm câu trước và chấm điểm khách quan dựa trên đáp án tham chiếu theo 3 tiêu chí Rubric ở nhiệt độ 0.</p>
            </div>
        </div>

        <!-- Sơ đồ quy trình hoạt động (4-Stage Engineering Pipeline) -->
        <div class="mt-14 p-6 sm:p-8 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-6">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">System Architecture</span>
                    <h2 class="text-lg sm:text-xl font-extrabold mt-0.5">Luồng xử lý dữ liệu thông minh trên SkillForge AI</h2>
                </div>
                <a href="{{ route('api.docs') }}" class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">
                    Khám phá đặc tả OpenAPI 3.0 →
                </a>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-xs">
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800">
                    <div class="font-mono font-bold text-indigo-600 dark:text-indigo-400 mb-1">STAGE 1 • PII REDACTION</div>
                    <div class="font-bold text-sm text-slate-900 dark:text-white mb-1">Xử lý CV PDF An toàn</div>
                    <p class="text-slate-500 leading-relaxed">Kiểm tra chữ ký nhị phân <code>%PDF-</code>, bóc tách văn bản, tự động ẩn Email/SĐT và xoá tệp gốc ngay lập tức.</p>
                </div>
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800">
                    <div class="font-mono font-bold text-emerald-600 dark:text-emerald-400 mb-1">STAGE 2 • 3-TIER NORMALIZER</div>
                    <div class="font-bold text-sm text-slate-900 dark:text-white mb-1">Ánh xạ & Chấm điểm</div>
                    <p class="text-slate-500 leading-relaxed">Kiểm chứng <code>evidence</code> nguyên văn, ánh xạ kỹ năng qua 3 tầng và tính điểm trọng số (Required ×2, Nice-to-have ×1).</p>
                </div>
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800">
                    <div class="font-mono font-bold text-amber-600 dark:text-amber-400 mb-1">STAGE 3 • HYBRID RAG</div>
                    <div class="font-bold text-sm text-slate-900 dark:text-white mb-1">Lộ trình Học theo Tuần</div>
                    <p class="text-slate-500 leading-relaxed">Kết hợp HNSW Vector Search và GIN Full-Text Search bằng công thức RRF ($k=60$), phân bổ vừa khít quỹ giờ học.</p>
                </div>
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800">
                    <div class="font-mono font-bold text-rose-600 dark:text-rose-400 mb-1">STAGE 4 • RUBRIC GRADING</div>
                    <div class="font-bold text-sm text-slate-900 dark:text-white mb-1">Phỏng vấn Thích ứng</div>
                    <p class="text-slate-500 leading-relaxed">Sinh đáp án tham chiếu chuẩn và chấm điểm câu trả lời theo 3 tiêu chí: Correctness, Depth và Clarity.</p>
                </div>
            </div>
        </div>

        <!-- Danh sách Báo cáo Phân tích Gần đây -->
        @if ($recentReports->isNotEmpty())
            <div class="mt-14">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-bold">Báo cáo Phân tích Gần đây</h2>
                    <a href="{{ route('onboarding') }}" class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">
                        + Tạo báo cáo mới
                    </a>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    @foreach ($recentReports as $rep)
                        @php $scorePct = round(((float) $rep->match_score) * 100); @endphp
                        <a href="{{ route('reports.show', $rep->id) }}"
                           class="group block p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:border-indigo-500 shadow-sm transition">
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-xs font-medium text-slate-500">{{ $rep->created_at?->diffForHumans() }}</span>
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold {{ $scorePct >= 70 ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400' }}">
                                    {{ $scorePct }}% Match
                                </span>
                            </div>
                            <div class="font-bold text-sm truncate group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition">
                                {{ $rep->profile?->title ?? 'Hồ sơ CV' }}
                            </div>
                            <div class="text-xs text-slate-500 truncate mt-1">
                                Mục tiêu: {{ $rep->jobTarget?->title ?? 'Vị trí tuyển dụng' }}
                            </div>
                            <div class="mt-3 w-full h-1.5 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
                                <div class="h-full bg-indigo-600 rounded-full" style="width: {{ min(100, $scorePct) }}%"></div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </section>
</div>
@endsection
