@extends('layouts.app')

@section('title', 'Thiết lập Phân tích — SkillForge AI')

@section('content')
<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <div class="mb-8">
        <span class="text-xs font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">SkillForge AI Pipeline</span>
        <h1 class="text-2xl sm:text-3xl font-extrabold mt-1">Thiết lập Hồ sơ CV & Mục tiêu Tuyển dụng</h1>
        <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">
            Tải lên CV PDF của bạn (tự động ẩn Email & SĐT, xoá tệp gốc ngay sau khi bóc tách văn bản) hoặc chọn hồ sơ mẫu để trải nghiệm toàn trình.
        </p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- CỘT 1: Chọn nguồn CV (Upload PDF hoặc Chọn CV Mẫu) -->
        <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <h2 class="text-lg font-bold mb-4 flex items-center gap-2">
                <span class="w-6 h-6 rounded-full bg-indigo-600 text-white text-xs flex items-center justify-center">1</span>
                <span>Hồ sơ Ứng viên (CV)</span>
            </h2>

            <!-- Chuyển tab CV Mẫu / Tải lên PDF -->
            <div class="flex gap-2 mb-4 p-1 rounded-xl bg-slate-100 dark:bg-slate-800 text-xs font-semibold">
                <button type="button" id="tab-cv-sample" onclick="switchCvMode('sample')"
                        class="flex-1 py-2 rounded-lg bg-white dark:bg-slate-900 shadow-sm text-indigo-600 dark:text-indigo-400 cursor-pointer">
                    Dùng CV Mẫu có sẵn
                </button>
                <button type="button" id="tab-cv-upload" onclick="switchCvMode('upload')"
                        class="flex-1 py-2 rounded-lg text-slate-600 dark:text-slate-400 cursor-pointer">
                    Tải lên tệp PDF (≤ {{ config('ai.max_upload_mb', 5) }}MB)
                </button>
            </div>

            <!-- Khối chọn CV mẫu -->
            <div id="panel-cv-sample" class="space-y-3">
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500">Chọn 1 trong 3 hồ sơ ứng viên mẫu:</label>
                @foreach ($sampleCvs as $key => $label)
                    <label class="flex items-start gap-3 p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 hover:border-indigo-500 cursor-pointer transition">
                        <input type="radio" name="sample_id" value="{{ $key }}"
                               class="mt-1 text-indigo-600 focus:ring-indigo-500"
                               {{ ($preselectedSample ?: 'fresher-php') === $key ? 'checked' : '' }}>
                        <div>
                            <div class="text-sm font-semibold">{{ $label }}</div>
                            <div class="text-xs text-slate-500 mt-0.5">Mã hồ sơ: <code>{{ $key }}</code></div>
                        </div>
                    </label>
                @endforeach
            </div>

            <!-- Khối Dropzone Upload PDF hỗ trợ kéo thả trực tiếp -->
            <div id="panel-cv-upload" class="hidden">
                <label id="cv-dropzone" for="cv-file-input"
                       class="flex flex-col items-center justify-center border-2 border-dashed border-slate-300 dark:border-slate-700 hover:border-indigo-500 rounded-2xl p-8 text-center cursor-pointer transition">
                    <svg class="w-10 h-10 text-indigo-500 mb-3" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                    </svg>
                    <span class="text-sm font-semibold">Bấm để chọn hoặc kéo thả tệp CV (.pdf) vào đây</span>
                    <span id="selected-file-name" class="text-xs text-slate-500 mt-1">Kiểm tra chữ ký %PDF-, tối đa {{ config('ai.max_upload_mb', 5) }} MB</span>
                    <input id="cv-file-input" type="file" accept="application/pdf,.pdf" class="hidden">
                </label>
            </div>
        </div>

        <!-- CỘT 2: Chọn Vị trí mẫu (Role) hoặc Dán JD tự do -->
        <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <h2 class="text-lg font-bold mb-4 flex items-center gap-2">
                <span class="w-6 h-6 rounded-full bg-indigo-600 text-white text-xs flex items-center justify-center">2</span>
                <span>Mục tiêu Tuyển dụng (Role / JD)</span>
            </h2>

            <div class="flex gap-2 mb-4 p-1 rounded-xl bg-slate-100 dark:bg-slate-800 text-xs font-semibold">
                <button type="button" id="tab-jd-role" onclick="switchJdMode('role')"
                        class="flex-1 py-2 rounded-lg bg-white dark:bg-slate-900 shadow-sm text-indigo-600 dark:text-indigo-400 cursor-pointer">
                    Chọn Vị trí Chuẩn (5 Roles)
                </button>
                <button type="button" id="tab-jd-custom" onclick="switchJdMode('custom')"
                        class="flex-1 py-2 rounded-lg text-slate-600 dark:text-slate-400 cursor-pointer">
                    Dán JD Tuyển dụng Tự do
                </button>
            </div>

            <!-- Khối chọn Role mẫu -->
            <div id="panel-jd-role" class="space-y-2.5">
                @foreach ($roles as $idx => $role)
                    <label class="flex items-start gap-3 p-3 rounded-xl border border-slate-200 dark:border-slate-800 hover:border-indigo-500 cursor-pointer transition">
                        <input type="radio" name="role_slug" value="{{ $role['slug'] }}"
                               class="mt-1 text-indigo-600 focus:ring-indigo-500"
                               {{ $role['slug'] === 'fullstack' || ($idx === 0 && $role['slug'] !== 'fullstack') ? 'checked' : '' }}>
                        <div>
                            <div class="text-sm font-semibold">{{ $role['title'] }}</div>
                            <div class="text-xs text-slate-500 line-clamp-2">{{ $role['description'] ?? '' }}</div>
                        </div>
                    </label>
                @endforeach
            </div>

            <!-- Khối dán JD tự do -->
            <div id="panel-jd-custom" class="hidden space-y-3">
                <div class="flex items-center justify-between">
                    <label class="text-xs font-semibold text-slate-500">Hoặc nạp nhanh JD mẫu:</label>
                    <select id="sample-jd-selector" onchange="fillSampleJd(this.value)"
                            class="text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-transparent px-2 py-1">
                        <option value="">-- Chọn JD mẫu để điền nhanh --</option>
                        @foreach ($sampleJds as $jdKey => $jdItem)
                            <option value="{{ $jdKey }}">{{ $jdItem['title'] }}</option>
                        @endforeach
                    </select>
                </div>

                <input type="text" id="jd-title-input" placeholder="Tên vị trí (VD: Junior Laravel Developer)"
                       class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-transparent px-3.5 py-2 text-sm">

                <textarea id="jd-text-input" rows="6" placeholder="Dán toàn bộ nội dung mô tả công việc (JD) vào đây..."
                          class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-transparent p-3.5 text-sm"></textarea>
            </div>
        </div>
    </div>

    <!-- Hộp hiển thị lỗi nếu có -->
    <div id="onboarding-error" class="hidden mt-6 p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-600 dark:text-rose-300 text-sm"></div>

    <!-- Khung Skeleton Loading & Thanh tiến trình khi đang gọi AI xử lý -->
    <div id="onboarding-progress-box" class="hidden mt-6 p-6 rounded-2xl bg-white dark:bg-slate-900 border border-indigo-500/30 shadow-sm">
        <div class="flex items-center justify-between mb-2">
            <span id="progress-status-text" class="text-sm font-semibold text-indigo-600 dark:text-indigo-400">
                Đang khởi tạo tiến trình phân tích AI...
            </span>
            <span id="progress-percent" class="text-xs font-mono font-bold">10%</span>
        </div>
        <div class="w-full h-2.5 rounded-full bg-slate-200 dark:bg-slate-800 overflow-hidden mb-4">
            <div id="progress-bar-fill" class="h-full bg-indigo-600 transition-all duration-300" style="width: 10%"></div>
        </div>
        <div class="space-y-2.5 skeleton-loading">
            <div class="h-4 bg-slate-200 dark:bg-slate-800 rounded w-3/4"></div>
            <div class="h-4 bg-slate-200 dark:bg-slate-800 rounded w-1/2"></div>
            <div class="h-4 bg-slate-200 dark:bg-slate-800 rounded w-5/6"></div>
        </div>
    </div>

    <!-- Nút bấm chạy phân tích -->
    <div class="mt-8 flex flex-col sm:flex-row items-center justify-end gap-4">
        <button type="button" id="start-analysis-btn" onclick="submitOnboarding()"
                class="w-full sm:w-auto px-8 py-3.5 rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-700 hover:to-violet-700 text-white font-bold shadow-lg shadow-indigo-500/25 transition cursor-pointer">
            Phân tích Kỹ năng & Tạo Báo cáo Gap ngay →
        </button>
    </div>
</div>

@push('scripts')
<script>
    let cvMode = 'sample';
    let jdMode = 'role';
    const sampleJdsData = @json($sampleJds);

    const fileInput = document.getElementById('cv-file-input');
    const dropzone = document.getElementById('cv-dropzone');

    fileInput?.addEventListener('change', function (e) {
        const file = e.target.files?.[0];
        if (file) {
            document.getElementById('selected-file-name').textContent = `Đã chọn: ${file.name} (${(file.size / 1024).toFixed(1)} KB)`;
        }
    });

    // Hỗ trợ kéo thả tệp PDF trực tiếp vào vùng dropzone
    if (dropzone && fileInput) {
        ['dragenter', 'dragover'].forEach(evtName => {
            dropzone.addEventListener(evtName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropzone.classList.add('border-indigo-500', 'bg-indigo-500/5');
            });
        });
        ['dragleave', 'drop'].forEach(evtName => {
            dropzone.addEventListener(evtName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropzone.classList.remove('border-indigo-500', 'bg-indigo-500/5');
            });
        });
        dropzone.addEventListener('drop', (e) => {
            const files = e.dataTransfer?.files;
            if (files && files.length > 0) {
                fileInput.files = files;
                document.getElementById('selected-file-name').textContent = `Đã chọn: ${files[0].name} (${(files[0].size / 1024).toFixed(1)} KB)`;
            }
        });
    }

    function switchCvMode(mode) {
        cvMode = mode;
        document.getElementById('panel-cv-sample').classList.toggle('hidden', mode !== 'sample');
        document.getElementById('panel-cv-upload').classList.toggle('hidden', mode !== 'upload');
        document.getElementById('tab-cv-sample').className = mode === 'sample'
            ? 'flex-1 py-2 rounded-lg bg-white dark:bg-slate-900 shadow-sm text-indigo-600 dark:text-indigo-400 cursor-pointer'
            : 'flex-1 py-2 rounded-lg text-slate-600 dark:text-slate-400 cursor-pointer';
        document.getElementById('tab-cv-upload').className = mode === 'upload'
            ? 'flex-1 py-2 rounded-lg bg-white dark:bg-slate-900 shadow-sm text-indigo-600 dark:text-indigo-400 cursor-pointer'
            : 'flex-1 py-2 rounded-lg text-slate-600 dark:text-slate-400 cursor-pointer';
    }

    function switchJdMode(mode) {
        jdMode = mode;
        document.getElementById('panel-jd-role').classList.toggle('hidden', mode !== 'role');
        document.getElementById('panel-jd-custom').classList.toggle('hidden', mode !== 'custom');
        document.getElementById('tab-jd-role').className = mode === 'role'
            ? 'flex-1 py-2 rounded-lg bg-white dark:bg-slate-900 shadow-sm text-indigo-600 dark:text-indigo-400 cursor-pointer'
            : 'flex-1 py-2 rounded-lg text-slate-600 dark:text-slate-400 cursor-pointer';
        document.getElementById('tab-jd-custom').className = mode === 'custom'
            ? 'flex-1 py-2 rounded-lg bg-white dark:bg-slate-900 shadow-sm text-indigo-600 dark:text-indigo-400 cursor-pointer'
            : 'flex-1 py-2 rounded-lg text-slate-600 dark:text-slate-400 cursor-pointer';
    }

    function fillSampleJd(key) {
        if (!key || !sampleJdsData[key]) return;
        document.getElementById('jd-title-input').value = sampleJdsData[key].title;
        document.getElementById('jd-text-input').value = sampleJdsData[key].content;
    }

    async function pollJobUntilDone(jobId, statusPrefix) {
        for (let i = 0; i < 90; i++) {
            const res = await fetch(`/api/v1/jobs/${jobId}`, { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            if (!res.ok) {
                throw new Error(data?.error?.message || 'Lỗi khi kiểm tra tiến trình.');
            }
            document.getElementById('progress-status-text').textContent = `${statusPrefix} (${data.state})...`;
            document.getElementById('progress-percent').textContent = `${data.progress || 50}%`;
            document.getElementById('progress-bar-fill').style.width = `${data.progress || 50}%`;

            if (data.state === 'completed') {
                return data;
            }
            if (data.state === 'failed') {
                throw new Error(data.error || 'Tiến trình xử lý thất bại.');
            }
            await new Promise(r => setTimeout(r, 1000));
        }
        throw new Error('Quá thời gian chờ xử lý job.');
    }

    async function submitOnboarding() {
        const errBox = document.getElementById('onboarding-error');
        const progBox = document.getElementById('onboarding-progress-box');
        const btn = document.getElementById('start-analysis-btn');
        errBox.classList.add('hidden');
        progBox.classList.remove('hidden');
        btn.disabled = true;

        try {
            // Bước 1: Tạo Profile từ CV mẫu hoặc file PDF
            document.getElementById('progress-status-text').textContent = 'Bước 1/3: Đang trích xuất kỹ năng & kiểm chứng trích dẫn từ CV...';
            let profileRes;
            if (cvMode === 'upload') {
                if (!fileInput.files.length) {
                    throw new Error('Vui lòng chọn một tệp PDF trước khi tiếp tục.');
                }
                const formData = new FormData();
                formData.append('file', fileInput.files[0]);
                profileRes = await fetch('/api/v1/profiles', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json' },
                    body: formData
                });
            } else {
                const selectedSample = document.querySelector('input[name="sample_id"]:checked')?.value || 'fresher-php';
                profileRes = await fetch('/api/v1/profiles', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ sampleId: selectedSample })
                });
            }

            const profileData = await profileRes.json();
            if (!profileRes.ok) {
                throw new Error(profileData?.error?.message || 'Không thể xử lý CV.');
            }
            await pollJobUntilDone(profileData.jobId, 'Bước 1/3: Chuẩn hoá kỹ năng CV 3 tầng');

            // Bước 2: Tạo JobTarget từ Role mẫu hoặc JD dán vào
            document.getElementById('progress-status-text').textContent = 'Bước 2/3: Đang phân tích yêu cầu tuyển dụng (JD)...';
            let jdPayload = {};
            if (jdMode === 'role') {
                jdPayload = { roleSlug: document.querySelector('input[name="role_slug"]:checked')?.value || 'fullstack' };
            } else {
                jdPayload = {
                    title: document.getElementById('jd-title-input').value,
                    jdText: document.getElementById('jd-text-input').value
                };
            }

            const jdRes = await fetch('/api/v1/job-targets', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify(jdPayload)
            });
            const jdData = await jdRes.json();
            if (!jdRes.ok) {
                throw new Error(jdData?.error?.message || 'Không thể phân tích mục tiêu công việc.');
            }
            await pollJobUntilDone(jdData.jobId, 'Bước 2/3: Phân tích yêu cầu JD');

            // Bước 3: Tạo Báo cáo Skill Gap
            document.getElementById('progress-status-text').textContent = 'Bước 3/3: Đang tính điểm tương thích & đối chiếu khoảng cách kỹ năng...';
            const repRes = await fetch('/api/v1/reports', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({
                    profileId: profileData.profileId,
                    jobTargetId: jdData.jobTargetId
                })
            });
            const repData = await repRes.json();
            if (!repRes.ok) {
                throw new Error(repData?.error?.message || 'Không thể tạo báo cáo Skill Gap.');
            }
            await pollJobUntilDone(repData.jobId, 'Bước 3/3: Hoàn thiện báo cáo Skill Gap');

            // Chuyển hướng tới trang báo cáo
            window.location.href = `/reports/${repData.reportId}`;
        } catch (err) {
            errBox.textContent = err.message || 'Đã xảy ra lỗi không xác định.';
            errBox.classList.remove('hidden');
            progBox.classList.add('hidden');
            btn.disabled = false;
        }
    }
</script>
@endpush
@endsection
