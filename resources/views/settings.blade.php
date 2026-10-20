@extends('layouts.app')

@section('title', 'Cài đặt Hệ thống & Quản lý Dữ liệu Cá nhân — SkillForge AI')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <h1 class="text-2xl sm:text-3xl font-extrabold mb-2">Cài đặt Hệ thống & Quyền riêng tư</h1>
    <p class="text-sm text-slate-600 dark:text-slate-400 mb-8">
        Cấu hình ngôn ngữ hiển thị, quỹ thời gian học mỗi tuần, thông tin Model AI đang kết nối và xoá toàn bộ dữ liệu cá nhân (cascade).
    </p>

    @if (session('status'))
        <div class="mb-6 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-700 dark:text-emerald-300 text-sm font-medium">
            {{ session('status') }}
        </div>
    @endif

    <div class="space-y-6">
        <!-- Cài đặt Ngôn ngữ & Số giờ học mỗi tuần -->
        <form method="POST" action="{{ route('settings.update') }}"
              class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-5">
            @csrf
            <h2 class="font-bold text-lg">Tuỳ chọn Cá nhân hoá</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500 mb-1.5">Ngôn ngữ giao diện mặc định</label>
                    <select name="locale" class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-transparent px-3.5 py-2.5 text-sm">
                        <option value="vi" {{ $currentLocale === 'vi' ? 'selected' : '' }}>Tiếng Việt (Mặc định)</option>
                        <option value="en" {{ $currentLocale === 'en' ? 'selected' : '' }}>English (Tiếng Anh)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500 mb-1.5">Quỹ thời gian học mỗi tuần (hoursPerWeek)</label>
                    <input type="number" name="hours_per_week" min="2" max="60" value="{{ $hoursPerWeek }}"
                           class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-transparent px-3.5 py-2.5 text-sm font-semibold">
                </div>
            </div>

            <div class="pt-2">
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold cursor-pointer">
                    Lưu cài đặt
                </button>
            </div>
        </form>

        <!-- Thông tin Model AI mở (Ollama) -->
        <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <h2 class="font-bold text-lg mb-3">Cấu hình Model AI Nguồn mở (Ollama)</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800">
                    <div class="text-slate-400">Chat / Reasoning Model</div>
                    <div class="font-mono font-bold text-sm mt-1 text-indigo-600 dark:text-indigo-400">{{ $chatModel }}</div>
                </div>
                <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800">
                    <div class="text-slate-400">Vector Embedding Model (1024-d)</div>
                    <div class="font-mono font-bold text-sm mt-1 text-emerald-600 dark:text-emerald-400">{{ $embedModel }}</div>
                </div>
                <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800">
                    <div class="text-slate-400">OpenAI-Compatible Endpoint</div>
                    <div class="font-mono font-bold text-sm mt-1 truncate">{{ config('ai.base_url') }}</div>
                </div>
            </div>
        </div>

        <!-- Quản lý & Xoá dữ liệu CV (Cascade Delete) -->
        <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-rose-500/30 shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h2 class="font-bold text-lg text-rose-600 dark:text-rose-400">Xoá Dữ liệu Cá nhân ({{ $profiles->count() }} hồ sơ hiện có)</h2>
                    <p class="text-xs text-slate-500 mt-1">
                        Khi xoá hồ sơ, toàn bộ kỹ năng trích xuất, báo cáo Skill Gap, lộ trình học và lịch sử phỏng vấn liên quan sẽ được xoá vĩnh viễn (Cascade Delete).
                    </p>
                </div>

                <form method="POST" action="{{ route('settings.purge') }}" onsubmit="return confirm('Bạn có chắc chắn muốn xoá toàn bộ hồ sơ CV và báo cáo liên quan?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold cursor-pointer">
                        Xoá toàn bộ dữ liệu ({{ $profiles->count() }})
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
