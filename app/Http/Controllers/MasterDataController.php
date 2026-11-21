<?php

namespace App\Http\Controllers;

use App\Http\Requests\MasterData\StoreSchoolClassRequest;
use App\Http\Requests\MasterData\StoreSubjectRequest;
use App\Models\SchoolClass;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class MasterDataController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('MasterData/Index', [
            'subjects' => Subject::query()
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'is_active']),
            'schoolClasses' => SchoolClass::query()
                ->orderByDesc('is_active')
                ->orderBy('level')
                ->orderBy('name')
                ->get(['id', 'name', 'level', 'is_active']),
        ]);
    }

    public function storeSubject(StoreSubjectRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        Subject::query()->create([
            'name' => trim($validated['name']),
            'code' => isset($validated['code']) ? trim((string) $validated['code']) : null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return redirect()
            ->route('master-data.index')
            ->with('success', 'Mata pelajaran berhasil ditambahkan.');
    }

    public function updateSubject(StoreSubjectRequest $request, Subject $subject): RedirectResponse
    {
        $validated = $request->validated();

        $subject->update([
            'name' => trim($validated['name']),
            'code' => isset($validated['code']) ? trim((string) $validated['code']) : null,
            'is_active' => $validated['is_active'] ?? $subject->is_active,
        ]);

        return redirect()
            ->route('master-data.index')
            ->with('success', "Mata pelajaran \"{$subject->name}\" berhasil diperbarui.");
    }

    public function storeSchoolClass(StoreSchoolClassRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        SchoolClass::query()->create([
            'name' => trim($validated['name']),
            'level' => isset($validated['level']) ? trim((string) $validated['level']) : null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return redirect()
            ->route('master-data.index')
            ->with('success', 'Data kelas berhasil ditambahkan.');
    }

    public function updateSchoolClass(StoreSchoolClassRequest $request, SchoolClass $schoolClass): RedirectResponse
    {
        $validated = $request->validated();

        $schoolClass->update([
            'name' => trim($validated['name']),
            'level' => isset($validated['level']) ? trim((string) $validated['level']) : null,
            'is_active' => $validated['is_active'] ?? $schoolClass->is_active,
        ]);

        return redirect()
            ->route('master-data.index')
            ->with('success', "Data kelas \"{$schoolClass->name}\" berhasil diperbarui.");
    }
}