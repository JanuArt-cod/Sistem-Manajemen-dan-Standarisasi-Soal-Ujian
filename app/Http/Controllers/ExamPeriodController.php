<?php

namespace App\Http\Controllers;

use App\Enums\ExamPeriodStatus;
use App\Enums\ExamSemester;
use App\Http\Requests\ExamPeriods\StoreExamPeriodRequest;
use App\Http\Requests\ExamPeriods\UpdateExamPeriodRequest;
use App\Models\ExamPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ExamPeriodController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ExamPeriod::class);

        $search = trim((string) $request->string('search', ''));
        $status = (string) $request->string('status', '');
        $academicYear = trim((string) $request->string('academic_year', ''));

        $periods = ExamPeriod::query()
            ->with('creator:id,name')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('academic_year', 'like', "%{$search}%");
                });
            })
            ->when(in_array($status, ExamPeriodStatus::values(), true), function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->when($academicYear !== '', function ($query) use ($academicYear) {
                $query->where('academic_year', $academicYear);
            })
            ->orderByRaw("CASE status WHEN 'ACTIVE' THEN 1 WHEN 'DRAFT' THEN 2 ELSE 3 END")
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (ExamPeriod $period) => [
                'id' => $period->id,
                'name' => $period->name,
                'academic_year' => $period->academic_year,
                'semester' => $period->semester->value,
                'semester_label' => $period->semester->label(),
                'start_date' => $period->start_date?->format('Y-m-d'),
                'start_date_formatted' => $period->start_date?->format('d M Y'),
                'end_date' => $period->end_date?->format('Y-m-d'),
                'end_date_formatted' => $period->end_date?->format('d M Y'),
                'status' => $period->status->value,
                'status_label' => $period->status->label(),
                'creator_name' => $period->creator?->name ?? '-',
                'can_edit' => $request->user()->can('update', $period),
                'can_archive' => $request->user()->can('archive', $period),
                'can_reopen' => $request->user()->can('reopen', $period),
            ]);

        $academicYears = ExamPeriod::query()
            ->select('academic_year')
            ->distinct()
            ->orderByDesc('academic_year')
            ->pluck('academic_year');

        return Inertia::render('ExamPeriods/Index', [
            'periods' => $periods,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'academic_year' => $academicYear,
            ],
            'academicYears' => $academicYears,
            'semesters' => collect(ExamSemester::cases())->map(fn (ExamSemester $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ])->values(),
            'statuses' => collect(ExamPeriodStatus::cases())->map(fn (ExamPeriodStatus $st) => [
                'value' => $st->value,
                'label' => $st->label(),
            ])->values(),
        ]);
    }

    public function store(StoreExamPeriodRequest $request): RedirectResponse
    {
        Gate::authorize('create', ExamPeriod::class);

        $validated = $request->validated();

        ExamPeriod::query()->create([
            'name' => $validated['name'],
            'academic_year' => $validated['academic_year'],
            'semester' => $validated['semester'],
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
            'status' => $validated['status'],
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('exam-periods.index')
            ->with('success', 'Periode ujian baru berhasil dibuat.');
    }

    public function update(UpdateExamPeriodRequest $request, ExamPeriod $examPeriod): RedirectResponse
    {
        Gate::authorize('update', $examPeriod);

        $validated = $request->validated();

        $examPeriod->update([
            'name' => $validated['name'],
            'academic_year' => $validated['academic_year'],
            'semester' => $validated['semester'],
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
            'status' => $validated['status'],
        ]);

        return redirect()
            ->route('exam-periods.index')
            ->with('success', "Periode ujian \"{$examPeriod->name}\" berhasil diperbarui.");
    }

    public function archive(ExamPeriod $examPeriod): RedirectResponse
    {
        Gate::authorize('archive', $examPeriod);

        $examPeriod->update([
            'status' => ExamPeriodStatus::ARCHIVED,
        ]);

        return redirect()
            ->route('exam-periods.index')
            ->with('success', "Periode ujian \"{$examPeriod->name}\" telah diarsipkan.");
    }

    public function reopen(ExamPeriod $examPeriod): RedirectResponse
    {
        Gate::authorize('reopen', $examPeriod);

        $examPeriod->update([
            'status' => ExamPeriodStatus::ACTIVE,
        ]);

        return redirect()
            ->route('exam-periods.index')
            ->with('success', "Periode ujian \"{$examPeriod->name}\" berhasil dibuka kembali sebagai Aktif.");
    }
}