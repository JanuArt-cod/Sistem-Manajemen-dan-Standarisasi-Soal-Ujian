<?php

namespace App\Http\Controllers;

use App\Enums\ExamPeriodStatus;
use App\Models\ExamPeriod;
use App\Models\SchoolClass;
use App\Models\SchoolProfile;
use App\Models\Subject;
use App\Models\User;
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

        $schoolProfile = SchoolProfile::current();

        return Inertia::render('Dashboard', [
            'stats' => [
                'active_periods' => ExamPeriod::query()->where('status', ExamPeriodStatus::ACTIVE)->count(),
                'active_subjects' => Subject::query()->where('is_active', true)->count(),
                'active_classes' => SchoolClass::query()->where('is_active', true)->count(),
                'active_users' => User::query()->where('is_active', true)->count(),
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