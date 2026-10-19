@extends('layouts.app')

@section('title', 'Phỏng vấn Kỹ thuật Thích ứng — SkillForge AI')

@section('content')
@php
    $turns = $session->turns;
    $activeTurn = $turns->whereNull('score')->first();
    $answeredTurns = $turns->whereNotNull('score');
@endphp

<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <!-- Thanh tiêu đề phiên phỏng vấn -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            @if ($session->gapReport)
                <a href="{{ route('reports.show', $session->gapReport->id) }}" class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">
                    ← Quay lại Báo cáo Skill Gap
                </a>
            @endif
            <h1 class="text-2xl sm:text-3xl font-extrabold mt-1">Phỏng vấn Kỹ thuật Thích ứng (Adaptive Mock Interview)</h1>
            <p class="text-xs text-slate-500 mt-1">
                Số câu hỏi: <strong>{{ $session->question_count }} câu</strong> • Chấm điểm khách quan theo Rubric (Correctness, Depth, Clarity) ở nhiệt độ 0.
            </p>
        </div>

        <div class="flex items-center gap-2">
            @if ($session->status === 'completed')
                <span class="px-3.5 py-1.5 rounded-full bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 text-xs font-extrabold">
                    Đã hoàn thành • Điểm TB: {{ $session->average_score ?? 0 }}/5.00
                </span>
            @else
                <span class="px-3.5 py-1.5 rounded-full bg-indigo-500/15 text-indigo-600 dark:text-indigo-400 text-xs font-bold">
                    Đang diễn ra ({{ $answeredTurns->count() }}/{{ $session->question_count }})
                </span>
            @endif
        </div>
    </div>

    <!-- Thanh Tiến trình Câu hỏi (Question Stepper 1 -> N) -->
    <div class="mb-8 grid grid-cols-5 gap-2">
        @for ($step = 1; $step <= $session->question_count; $step++)
            @php
                $stepTurn = $turns->firstWhere('turn_number', $step);
                $isDone = $stepTurn && $stepTurn->score !== null;
                $isCurrent = $activeTurn && $activeTurn->turn_number === $step;
            @endphp
            <div class="p-2.5 rounded-xl border text-center text-xs transition {{ $isDone ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-700 dark:text-emerald-300 font-bold' : ($isCurrent ? 'bg-indigo-600 border-indigo-600 text-white font-bold shadow-sm' : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 text-slate-400') }}">
                <div>Câu {{ $step }}</div>
                @if ($isDone)
                    <div class="text-[11px] mt-0.5">{{ $stepTurn->score }}/5 điểm</div>
                @elseif ($isCurrent)
                    <div class="text-[11px] mt-0.5">Đang trả lời</div>
                @else
                    <div class="text-[11px] mt-0.5">Chờ</div>
                @endif
            </div>
        @endfor
    </div>

    <!-- Màn hình Tổng kết khi hoàn thành đủ số câu hỏi -->
    @if ($session->status === 'completed')
        <div class="mb-8 p-6 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 shadow-sm">
            <h2 class="text-lg font-extrabold text-emerald-700 dark:text-emerald-300">🎉 Tổng kết Phiên Phỏng vấn Kỹ thuật</h2>
            <p class="text-sm text-slate-700 dark:text-slate-200 mt-1">{{ $session->summary }}</p>
            <div class="mt-4 flex flex-wrap gap-3">
                @if ($session->gapReport)
                    <a href="{{ route('reports.roadmap', $session->gapReport->id) }}"
                       class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold">
                        Ôn tập theo Lộ trình RAG →
                    </a>
                    <a href="{{ route('reports.show', $session->gapReport->id) }}"
                       class="px-4 py-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-xs font-semibold">
                        Xem lại Báo cáo Skill Gap
                    </a>
                @endif
            </div>
        </div>
    @endif

    <!-- Danh sách các lượt câu hỏi & chấm điểm Rubric -->
    <div class="space-y-6">
        @foreach ($turns as $turn)
            <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
                <!-- Header câu hỏi -->
                <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                    <div class="flex items-center gap-2">
                        <span class="px-2.5 py-1 rounded-lg bg-indigo-600 text-white text-xs font-extrabold">
                            Câu {{ $turn->turn_number }}/{{ $session->question_count }}
                        </span>
                        <span class="text-xs font-mono px-2.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 font-semibold">
                            #{{ $turn->skill_slug }}
                        </span>
                        <span class="text-xs px-2 py-0.5 rounded bg-amber-500/10 text-amber-700 dark:text-amber-300">
                            Loại: {{ $turn->question_type }}
                        </span>
                        <span class="text-xs px-2 py-0.5 rounded bg-sky-500/10 text-sky-700 dark:text-sky-300">
                            Độ khó: {{ strtoupper($turn->difficulty) }}
                        </span>
                    </div>

                    @if ($turn->score !== null)
                        <span class="px-3 py-1 rounded-full text-xs font-extrabold {{ $turn->score >= 4 ? 'bg-emerald-500/15 text-emerald-600' : ($turn->score >= 3 ? 'bg-amber-500/15 text-amber-600' : 'bg-rose-500/15 text-rose-600') }}">
                            Điểm: {{ $turn->score }}/5
                        </span>
                    @endif
                </div>

                <!-- Nội dung câu hỏi của AI Interviewer -->
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-sm font-medium leading-relaxed">
                    {{ $turn->question }}
                </div>

                <!-- Nếu câu hỏi đã được trả lời -> Hiển thị câu trả lời của ứng viên và kết quả chấm Rubric -->
                @if ($turn->score !== null)
                    <div class="mt-4 pl-4 border-l-2 border-indigo-500">
                        <div class="text-xs font-bold text-slate-400 uppercase">Câu trả lời của bạn:</div>
                        <p class="text-sm text-slate-700 dark:text-slate-300 mt-1 whitespace-pre-wrap">{{ $turn->user_answer }}</p>
                    </div>

                    @php $grade = is_array($turn->grade) ? $turn->grade : []; @endphp
                    <div class="mt-4 p-4 rounded-xl bg-indigo-500/5 border border-indigo-500/20 space-y-3 text-xs">
                        <!-- 3 Tiêu chí Rubric -->
                        <div class="grid grid-cols-3 gap-3 text-center">
                            <div class="p-2.5 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                                <div class="text-slate-400">Correctness (Độ chính xác)</div>
                                <div class="text-base font-extrabold text-indigo-600 dark:text-indigo-400 mt-0.5">{{ $grade['criteria']['correctness'] ?? $turn->score }}/5</div>
                            </div>
                            <div class="p-2.5 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                                <div class="text-slate-400">Depth (Chiều sâu)</div>
                                <div class="text-base font-extrabold text-indigo-600 dark:text-indigo-400 mt-0.5">{{ $grade['criteria']['depth'] ?? $turn->score }}/5</div>
                            </div>
                            <div class="p-2.5 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                                <div class="text-slate-400">Clarity (Độ mạch lạc)</div>
                                <div class="text-base font-extrabold text-indigo-600 dark:text-indigo-400 mt-0.5">{{ $grade['criteria']['clarity'] ?? $turn->score }}/5</div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <div class="font-bold text-emerald-600 dark:text-emerald-400 mb-1">✓ Điểm mạnh (Strengths):</div>
                                <ul class="list-disc list-inside space-y-1 text-slate-600 dark:text-slate-300">
                                    @foreach ($grade['strengths'] ?? [] as $s)
                                        <li>{{ $s }}</li>
                                    @endforeach
                                </ul>
                            </div>
                            <div>
                                <div class="font-bold text-amber-600 dark:text-amber-400 mb-1">⚠ Điểm cần bổ sung (Missing Points):</div>
                                <ul class="list-disc list-inside space-y-1 text-slate-600 dark:text-slate-300">
                                    @foreach ($grade['missing_points'] ?? [] as $mp)
                                        <li>{{ $mp }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>

                        @if (! empty($grade['sample_answer']))
                            <div class="pt-2 border-t border-slate-200 dark:border-slate-800">
                                <span class="font-bold text-indigo-600 dark:text-indigo-400">💡 Câu trả lời mẫu (Sample Answer):</span>
                                <p class="mt-1 text-slate-600 dark:text-slate-300 leading-relaxed">{{ $grade['sample_answer'] }}</p>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    <!-- Khung nhập câu trả lời cho câu hỏi hiện tại -->
    @if ($activeTurn && $session->status !== 'completed')
        <div class="mt-6 p-6 rounded-2xl bg-white dark:bg-slate-900 border-2 border-indigo-500/40 shadow-sm">
            <div class="flex items-center justify-between mb-2">
                <label for="interview-answer-input" class="block text-sm font-bold">
                    Nhập câu trả lời của bạn cho Câu {{ $activeTurn->turn_number }}:
                </label>
                <span class="text-[11px] font-mono text-slate-400">Mẹo: Nhấn Ctrl + Enter để gửi nhanh</span>
            </div>
            <textarea id="interview-answer-input" rows="5"
                      placeholder="Trình bày nguyên lý kỹ thuật, cách triển khai thực tế và cách xử lý các trường hợp biên..."
                      class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-transparent p-3.5 text-sm"></textarea>

            <!-- Trạng thái Skeleton Loading khi đang chấm điểm -->
            <div id="interview-grading-box" class="hidden mt-4 p-4 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800">
                <div id="interview-grading-status" class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 mb-2">
                    Đang đối chiếu với đáp án tham chiếu và chấm điểm theo Rubric (nhiệt độ 0)...
                </div>
                <div class="space-y-2 skeleton-loading">
                    <div class="h-3 bg-slate-200 dark:bg-slate-800 rounded w-2/3"></div>
                    <div class="h-3 bg-slate-200 dark:bg-slate-800 rounded w-1/2"></div>
                </div>
            </div>

            <div class="mt-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <span class="text-xs text-slate-400">
                    Đạt ≥ 4 điểm câu sau sẽ tăng độ khó; ≤ 2 điểm câu sau sẽ giữ cùng kỹ năng ở mức dễ hơn.
                </span>
                <button type="button" id="submit-answer-btn" onclick="submitInterviewAnswer('{{ $activeTurn->id }}')"
                        class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold shadow-sm cursor-pointer">
                    Gửi câu trả lời & Chấm điểm →
                </button>
            </div>
        </div>
    @endif
</div>

@push('scripts')
<script>
    // Hỗ trợ phím tắt Ctrl + Enter để gửi câu trả lời phỏng vấn
    document.getElementById('interview-answer-input')?.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            e.preventDefault();
            document.getElementById('submit-answer-btn')?.click();
        }
    });

    async function submitInterviewAnswer(turnId) {
        const answerInput = document.getElementById('interview-answer-input');
        const answer = answerInput?.value?.trim() || '';
        if (answer.length < 3) {
            alert('Vui lòng nhập câu trả lời trước khi gửi.');
            return;
        }

        const btn = document.getElementById('submit-answer-btn');
        const gradingBox = document.getElementById('interview-grading-box');
        btn.disabled = true;
        gradingBox.classList.remove('hidden');

        try {
            const res = await fetch('/api/v1/interviews/{{ $session->id }}/answers', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ turnId, answer })
            });
            const data = await res.json();
            if (res.ok) {
                window.location.reload();
            } else {
                alert(data?.error?.message || 'Lỗi khi chấm câu trả lời.');
                btn.disabled = false;
                gradingBox.classList.add('hidden');
            }
        } catch (e) {
            btn.disabled = false;
            gradingBox.classList.add('hidden');
        }
    }
</script>
@endpush
@endsection
