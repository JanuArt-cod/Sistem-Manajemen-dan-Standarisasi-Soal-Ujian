<?php

namespace App\Http\Controllers;

use App\Services\Maintenance\SystemMaintenanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class SystemMaintenanceController extends Controller
{
    public function index(Request $request, SystemMaintenanceService $maintenanceService): Response
    {
        abort_unless($request->user()?->isAdmin(), 403);

        return Inertia::render('SystemMaintenance/Index', [
            'summary' => $maintenanceService->getSummary(),
            'defaultsInfo' => [
                'preset_name' => SystemMaintenanceService::DEFAULT_PRESET_NAME,
                'template_name' => SystemMaintenanceService::DEFAULT_TEMPLATE_NAME,
            ],
        ]);
    }

    public function resetDefaults(Request $request, SystemMaintenanceService $maintenanceService): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $removeCustom = $request->boolean('remove_custom');
        $result = $maintenanceService->restoreDefaultPresetsAndTemplates(
            $request->user()->id,
            $removeCustom
        );

        $message = 'Preset Format F4 dan Template Kop Halaman Pertama berhasil dikembalikan ke standar default SIMSOAL.';
        if ($removeCustom) {
            $message .= " ({$result['removed_presets']} preset kustom & {$result['removed_templates']} template kustom dibersihkan).";
        }

        return back()->with('success', $message);
    }

    public function purgeDocuments(Request $request, SystemMaintenanceService $maintenanceService): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'confirmation' => ['required', 'string', 'in:HAPUS DOKUMEN'],
        ], [
            'confirmation.in' => 'Ketik tepat "HAPUS DOKUMEN" untuk mengonfirmasi pembersihan data uji coba.',
        ]);

        unset($validated);

        $result = $maintenanceService->purgeTestDocumentsAndFiles();

        return redirect()
            ->route('system-maintenance.index')
            ->with(
                'success',
                "Pembersihan selesai: {$result['deleted_documents']} dokumen soal dan {$result['deleted_files']} file fisik privat telah dihapus."
            );
    }

    public function purgeMasterData(Request $request, SystemMaintenanceService $maintenanceService): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $request->validate([
            'confirmation' => ['required', 'string', 'in:HAPUS MASTER'],
        ], [
            'confirmation.in' => 'Ketik tepat "HAPUS MASTER" untuk mengonfirmasi penghapusan data periode, mapel, dan kelas.',
        ]);

        try {
            $result = $maintenanceService->purgeTestMasterData();
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('system-maintenance.index')
            ->with(
                'success',
                "Data master uji coba dibersihkan ({$result['deleted_periods']} periode, {$result['deleted_subjects']} mapel, {$result['deleted_classes']} kelas)."
            );
    }
}