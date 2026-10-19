<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="SkillForge AI — Nền tảng phân tích khoảng cách kỹ năng (Skill Gap) có kiểm chứng bằng chứng nguyên văn, lộ trình học tập Hybrid RAG và luyện phỏng vấn kỹ thuật thích ứng.">
    <title>@yield('title', 'SkillForge AI — Phân tích Khoảng cách Kỹ năng, Lộ trình Hybrid RAG & Phỏng vấn Thích ứng')</title>

    <!-- Khởi tạo sớm chế độ Dark Mode từ localStorage tránh chớp màn hình -->
    <script>
        if (localStorage.getItem('acc_theme') === 'dark' || (!('acc_theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>

    <!-- Sử dụng Vite bundle khi đã build hoặc fallback sang Tailwind CDN để luôn hiển thị chuẩn xác -->
    @if (file_exists(public_path('build/manifest.json')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
        <script>
            tailwind.config = { darkMode: 'class' };
        </script>
    @endif
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js"></script>

    <!-- Tối ưu định dạng khi in hoặc xuất báo cáo ra PDF -->
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: #ffffff !important; color: #0f172a !important; }
        }
    </style>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100 flex flex-col antialiased selection:bg-indigo-600 selection:text-white">

    <!-- Banner thông báo trạng thái khởi tạo model AI từ endpoint /api/v1/health -->
    <div id="ai-model-banner" class="no-print hidden bg-amber-500/15 border-b border-amber-500/30 text-amber-900 dark:text-amber-200 px-4 py-2.5 text-sm">
        <div class="max-w-7xl mx-auto flex items-center justify-between gap-3">
            <div class="flex items-center gap-2.5">
                <span class="inline-block h-2.5 w-2.5 rounded-full bg-amber-500 animate-ping"></span>
                <span class="font-medium">{{ __('Đang tải model AI…') }}</span>
                <span id="ai-model-banner-detail" class="text-xs opacity-80 hidden sm:inline"></span>
            </div>
            <span class="text-xs font-mono px-2 py-0.5 rounded bg-amber-500/20">{{ config('ai.model') }} / {{ config('ai.embed_model') }}</span>
        </div>
    </div>

    <!-- Thanh điều hướng chính -->
    <header class="no-print sticky top-0 z-30 backdrop-blur-md bg-white/85 dark:bg-slate-900/85 border-b border-slate-200/80 dark:border-slate-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-2">
            <!-- Logo thương hiệu SkillForge AI -->
            <a href="{{ route('landing') }}" class="flex items-center gap-2.5 font-bold text-lg tracking-tight group">
                <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-600 to-violet-600 text-white shadow-sm shadow-indigo-500/30 group-hover:scale-105 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </span>
                <span class="truncate text-slate-900 dark:text-white">
                    SkillForge <span class="text-indigo-600 dark:text-indigo-400">AI</span>
                </span>
                <span class="hidden md:inline-block text-[11px] font-semibold px-2 py-0.5 rounded-full bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-500/20">
                    v1.0
                </span>
            </a>

            <!-- Menu điều hướng -->
            <nav class="flex items-center gap-1 sm:gap-2 text-sm font-medium">
                <a href="{{ route('landing') }}"
                   class="px-3 py-1.5 rounded-lg transition {{ request()->routeIs('landing') ? 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 font-semibold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800' }}">
                    {{ __('Trang chủ') }}
                </a>
                <a href="{{ route('onboarding') }}"
                   class="px-3 py-1.5 rounded-lg transition {{ request()->routeIs('onboarding') ? 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 font-semibold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800' }}">
                    {{ __('Bắt đầu phân tích') }}
                </a>
                <a href="{{ route('api.docs') }}"
                   class="hidden sm:inline-block px-3 py-1.5 rounded-lg transition {{ request()->routeIs('api.docs') ? 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 font-semibold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800' }}">
                    {{ __('Tài liệu API') }}
                </a>
                <a href="{{ route('settings') }}"
                   class="px-3 py-1.5 rounded-lg transition {{ request()->routeIs('settings') ? 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 font-semibold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800' }}">
                    {{ __('Cài đặt') }}
                </a>

                <div class="h-4 w-px bg-slate-200 dark:bg-slate-800 mx-1 hidden sm:block"></div>

                <!-- Chuyển đổi ngôn ngữ VI / EN -->
                <div class="flex items-center border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden text-xs">
                    <a href="{{ request()->fullUrlWithQuery(['lang' => 'vi']) }}"
                       class="px-2 py-1 {{ app()->getLocale() === 'vi' ? 'bg-indigo-600 text-white font-semibold' : 'hover:bg-slate-100 dark:hover:bg-slate-800' }}">VI</a>
                    <a href="{{ request()->fullUrlWithQuery(['lang' => 'en']) }}"
                       class="px-2 py-1 {{ app()->getLocale() === 'en' ? 'bg-indigo-600 text-white font-semibold' : 'hover:bg-slate-100 dark:hover:bg-slate-800' }}">EN</a>
                </div>

                <!-- Nút bật/tắt Dark Mode -->
                <button type="button" id="theme-toggle-btn" aria-label="Toggle Dark Mode"
                        class="p-2 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer">
                    <svg class="w-4 h-4 hidden dark:block text-amber-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                    <svg class="w-4 h-4 block dark:hidden text-slate-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                    </svg>
                </button>
            </nav>
        </div>
    </header>

    <!-- Nội dung chính -->
    <main class="flex-1">
        @yield('content')
    </main>

    <!-- Chân trang -->
    <footer class="no-print border-t border-slate-200 dark:border-slate-800 bg-white/50 dark:bg-slate-900/50 py-6 text-xs text-slate-500 dark:text-slate-400">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                <strong class="text-slate-800 dark:text-slate-200">SkillForge AI</strong>
                <span>—</span>
                <span>Career Intelligence & Adaptive Skill Gap Platform</span>
                <span class="px-2 py-0.5 rounded bg-slate-200/70 dark:bg-slate-800 font-mono text-[11px]">Apache-2.0</span>
            </div>
            <div class="flex items-center gap-4">
                <a href="{{ route('onboarding') }}" class="hover:text-indigo-600 dark:hover:text-indigo-400 transition">Phân tích CV</a>
                <a href="{{ route('api.docs') }}" class="hover:text-indigo-600 dark:hover:text-indigo-400 transition">OpenAPI Spec</a>
                <a href="/api/v1/health" target="_blank" class="hover:text-indigo-600 dark:hover:text-indigo-400 transition">System Health</a>
            </div>
        </div>
    </footer>

    <!-- Script kiểm tra /api/v1/health và bật/tắt Dark Mode -->
    <script>
        document.getElementById('theme-toggle-btn')?.addEventListener('click', function () {
            const isDark = document.documentElement.classList.toggle('dark');
            localStorage.setItem('acc_theme', isDark ? 'dark' : 'light');
        });

        async function checkAiModelHealth() {
            const banner = document.getElementById('ai-model-banner');
            const detail = document.getElementById('ai-model-banner-detail');
            if (!banner) return;
            try {
                const res = await fetch('/api/v1/health', { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                if (!res.ok || data.modelsReady === false) {
                    banner.classList.remove('hidden');
                    if (detail) {
                        detail.textContent = `(Ollama: ${data.ollama ?? 'checking'}, DB: ${data.database ?? 'up'})`;
                    }
                } else {
                    banner.classList.add('hidden');
                }
            } catch (e) {
                banner.classList.remove('hidden');
            }
        }
        checkAiModelHealth();
        setInterval(checkAiModelHealth, 15000);
    </script>

    @stack('scripts')
</body>
</html>
