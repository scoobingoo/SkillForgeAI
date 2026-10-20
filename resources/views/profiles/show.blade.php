@extends('layouts.app')

@section('title', 'Hồ sơ Kỹ năng — ' . ($profile->title ?? 'SkillForge AI'))

@section('content')
<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
        <div>
            <span class="text-xs font-mono uppercase px-2.5 py-1 rounded bg-indigo-500/10 text-indigo-600 dark:text-indigo-400">
                Hồ sơ Ứng viên (Đã ẩn PII)
            </span>
            <h1 class="text-2xl font-extrabold mt-2">{{ $profile->title }}</h1>
            <p class="text-xs text-slate-500 mt-1">ID: <code>{{ $profile->id }}</code> • Bạn có thể chỉnh sửa, thêm hoặc xoá kỹ năng thủ công bên dưới.</p>
        </div>

        @if ($profile->gapReports->isNotEmpty())
            <a href="{{ route('reports.show', $profile->gapReports->first()->id) }}"
               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold">
                <span>Xem Báo cáo Skill Gap</span>
                <span>→</span>
            </a>
        @endif
    </div>

    <div id="profile-save-alert" class="hidden mb-6 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-700 dark:text-emerald-300 text-sm"></div>

    <!-- Danh sách kỹ năng trích xuất được, cho phép sửa tay -->
    <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-bold text-lg">Kỹ năng Trích xuất ({{ $profile->skills->count() }})</h2>
            <button type="button" onclick="addSkillRow()"
                    class="px-3.5 py-1.5 rounded-lg bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-xs font-semibold cursor-pointer">
                + Thêm kỹ năng
            </button>
        </div>

        <div id="skills-rows-container" class="space-y-3">
            @foreach ($profile->skills as $skill)
                <div class="skill-row grid grid-cols-1 sm:grid-cols-12 gap-3 p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 items-center">
                    <div class="sm:col-span-3">
                        <label class="block text-[11px] text-slate-400">Tên kỹ năng</label>
                        <input type="text" class="skill-name w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent px-2.5 py-1.5 text-sm font-medium"
                               value="{{ $skill->raw_name }}">
                        <input type="hidden" class="skill-slug" value="{{ $skill->canonical_slug }}">
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-[11px] text-slate-400">Mức độ</label>
                        <select class="skill-level w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent px-2 py-1.5 text-sm">
                            <option value="beginner" {{ $skill->level === 'beginner' ? 'selected' : '' }}>Beginner</option>
                            <option value="intermediate" {{ $skill->level === 'intermediate' ? 'selected' : '' }}>Intermediate</option>
                            <option value="advanced" {{ $skill->level === 'advanced' ? 'selected' : '' }}>Advanced</option>
                        </select>
                    </div>

                    <div class="sm:col-span-5">
                        <label class="block text-[11px] text-slate-400">Trích dẫn nguyên văn (Evidence) • Mapping: <span class="font-mono">{{ $skill->mapping }}</span></label>
                        <input type="text" class="skill-evidence w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent px-2.5 py-1.5 text-xs text-slate-600 dark:text-slate-300"
                               value="{{ $skill->evidence }}">
                    </div>

                    <div class="sm:col-span-2 flex justify-end">
                        <button type="button" onclick="this.closest('.skill-row').remove()"
                                class="px-3 py-1.5 rounded-lg text-xs text-rose-600 hover:bg-rose-500/10 cursor-pointer">
                            Xoá
                        </button>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-6 flex justify-end">
            <button type="button" onclick="saveProfileSkills()"
                    class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold shadow-sm cursor-pointer">
                Lưu thay đổi kỹ năng
            </button>
        </div>
    </div>

    <!-- Văn bản CV gốc sau khi đã ẩn PII -->
    <div class="mt-8 p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
        <h3 class="font-bold text-sm uppercase tracking-wider text-slate-500 mb-3">Văn bản CV (Đã tự động che Email & SĐT)</h3>
        <pre class="text-xs whitespace-pre-wrap font-mono p-4 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300">{{ $profile->raw_text }}</pre>
    </div>
</div>

@push('scripts')
<script>
    function addSkillRow() {
        const container = document.getElementById('skills-rows-container');
        const div = document.createElement('div');
        div.className = 'skill-row grid grid-cols-1 sm:grid-cols-12 gap-3 p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 items-center';
        div.innerHTML = `
            <div class="sm:col-span-3">
                <label class="block text-[11px] text-slate-400">Tên kỹ năng</label>
                <input type="text" class="skill-name w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent px-2.5 py-1.5 text-sm font-medium" placeholder="VD: Docker">
                <input type="hidden" class="skill-slug" value="">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-[11px] text-slate-400">Mức độ</label>
                <select class="skill-level w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent px-2 py-1.5 text-sm">
                    <option value="beginner">Beginner</option>
                    <option value="intermediate" selected>Intermediate</option>
                    <option value="advanced">Advanced</option>
                </select>
            </div>
            <div class="sm:col-span-5">
                <label class="block text-[11px] text-slate-400">Trích dẫn (Evidence)</label>
                <input type="text" class="skill-evidence w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent px-2.5 py-1.5 text-xs" value="Bổ sung thủ công bởi ứng viên.">
            </div>
            <div class="sm:col-span-2 flex justify-end">
                <button type="button" onclick="this.closest('.skill-row').remove()" class="px-3 py-1.5 rounded-lg text-xs text-rose-600 hover:bg-rose-500/10 cursor-pointer">Xoá</button>
            </div>
        `;
        container.appendChild(div);
    }

    async function saveProfileSkills() {
        const rows = document.querySelectorAll('.skill-row');
        const skills = [];
        rows.forEach(row => {
            const raw_name = row.querySelector('.skill-name')?.value?.trim();
            if (!raw_name) return;
            skills.push({
                raw_name,
                canonical_slug: row.querySelector('.skill-slug')?.value?.trim() || null,
                level: row.querySelector('.skill-level')?.value || 'intermediate',
                evidence: row.querySelector('.skill-evidence')?.value?.trim() || 'Cập nhật thủ công bởi người dùng.'
            });
        });

        const res = await fetch('/api/v1/profiles/{{ $profile->id }}/skills', {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ skills })
        });

        if (res.ok) {
            const alertBox = document.getElementById('profile-save-alert');
            alertBox.textContent = 'Đã cập nhật danh sách kỹ năng thành công!';
            alertBox.classList.remove('hidden');
        }
    }
</script>
@endpush
@endsection
