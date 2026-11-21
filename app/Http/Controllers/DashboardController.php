<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Dashboard', [
            'stats' => [
                'active_users' => User::query()->where('is_active', true)->count(),
                'operator_count' => User::query()->where('role', UserRole::OPERATOR)->where('is_active', true)->count(),
                'admin_count' => User::query()->where('role', UserRole::ADMIN)->where('is_active', true)->count(),
            ],
            'systemStatus' => [
                'queue_driver' => config('queue.default'),
                'default_paper' => 'F4 (215 × 330 mm) Portrait',
                'default_font' => 'Times New Roman 12 pt',
            ],
        ]);
    }
}