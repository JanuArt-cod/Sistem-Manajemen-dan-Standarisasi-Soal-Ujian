<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Enums\ExamPeriodStatus;
use App\Models\ExamDocument;
use App\Models\ExamPeriod;
use App\Models\SchoolProfile;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $activePeriods = ExamPeriod::query()
            ->where('status', ExamPeriodStatus::ACTIVE)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'name', 'academic_year', 'semester', 'start_date', 'end_date']);

        $recentDocuments = ExamDocument::query()
            ->with(['examPeriod:id,name', 'subject:id,name', 'schoolClass:id,name'])
            ->latest('created_at')
            ->limit(5)
            ->get();

        $schoolProfile = SchoolProfile::current();

        return Inertia::render('Dashboard', [
            'stats' => [
                'active_periods' => ExamPeriod::query()->where('status', ExamPeriodStatus::ACTIVE)->count(),
                'total_documents' => ExamDocument::query()->count(),
                'needs_review_documents' => ExamDocument::query()->where('current_status', DocumentStatus::NEEDS_REVIEW)->count(),
                'approved_documents' => ExamDocument::query()->where('current_status', DocumentStatus::APPROVED)->count(),
            ],
            'activePeriods' => $activePeriods->map(fn (ExamPeriod $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'academic_year' => $p->academic_year,
                'semester_label' => $p->semester->label(),
                'date_range' => ($p->start_date && $p->end_date)
                    ? $p->start_date->format('d M Y').' – '.$p->end_date->format('d M Y')
                    : 'Jadwal belum diatur',
            ]),
            'recentDocuments' => $recentDocuments->map(fn (ExamDocument $d) => [
                'id' => $d->id,
                'title' => $d->document_title ?: 'Tanpa Judul',
                'teacher_name' => $d->teacher_name,
                'subject_name' => $d->subject?->name ?? 'Mapel belum diisi',
                'class_name' => $d->schoolClass?->name ?? 'Kelas belum diisi',
                'period_name' => $d->examPeriod?->name ?? '-',
                'status' => $d->current_status->value,
                'status_label' => $d->current_status->label(),
            ]),
            'schoolConfigured' => $schoolProfile !== null,
            'schoolName' => $schoolProfile?->institution_name,
            'systemStatus' => [
                'queue_driver' => config('queue.default'),
                'default_paper' => 'F4 (215 × 330 mm) Portrait',
                'default_font' => 'Times New Roman 12 pt',
            ],
        ]);
    }
}