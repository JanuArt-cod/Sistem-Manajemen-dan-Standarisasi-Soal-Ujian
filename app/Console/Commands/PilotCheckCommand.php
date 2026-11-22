<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\DocumentTemplate;
use App\Models\FormattingPreset;
use App\Models\User;
use App\Services\DocumentProcessing\LibreOfficePdfConverter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PilotCheckCommand extends Command
{
    protected $signature = 'simsoal:pilot-check';

    protected $description = 'Memeriksa kesiapan infrastruktur, mesin Python DOCX, LibreOffice, penyimpanan privat, dan data bawaan sebelum uji pilot SIMSOAL.';

    public function handle(LibreOfficePdfConverter $pdfConverter): int
    {
        $this->components->info('Pemeriksaan Kesiapan Sistem & Uji Pilot SIMSOAL');

        $failures = 0;

        // 1. Koneksi Database & Migrasi Inti
        $dbOk = $this->checkDatabase();
        $this->renderStatus('Koneksi Database & Tabel Inti', $dbOk, $dbOk ? 'Terhubung' : 'Gagal terhubung / tabel belum dimigrasi');
        if (! $dbOk) {
            $failures++;
        }

        // 2. Python Virtual Environment & Modul Mesin DOCX
        [$pythonOk, $pythonDetail] = $this->checkPythonEngine();
        $this->renderStatus('Python CLI & Modul (python-docx, Pillow)', $pythonOk, $pythonDetail);
        if (! $pythonOk) {
            $failures++;
        }

        // 3. LibreOffice Headless untuk Ekspor PDF F4
        $loVersion = $pdfConverter->detectVersion();
        $loOk = $loVersion !== null;
        $this->renderStatus(
            'Konverter PDF LibreOffice',
            $loOk,
            $loOk ? $loVersion : 'Binary LibreOffice tidak ditemukan di SIMSOAL_LIBREOFFICE_BINARY'
        );
        if (! $loOk) {
            $failures++;
        }

        // 4. Penyimpanan Privat (Disk local)
        [$storageOk, $storageDetail] = $this->checkPrivateStorage();
        $this->renderStatus('Penyimpanan Dokumen Privat', $storageOk, $storageDetail);
        if (! $storageOk) {
            $failures++;
        }

        // 5. Preset Default F4 & Template Kop Halaman Pertama (D-016)
        if ($dbOk) {
            $defaultPreset = FormattingPreset::query()->where('is_default', true)->where('is_active', true)->first();
            $presetOk = $defaultPreset !== null;
            $this->renderStatus(
                'Preset Standar Default (F4 215x330 mm)',
                $presetOk,
                $presetOk ? "{$defaultPreset->name} ({$defaultPreset->paper_width_mm}x{$defaultPreset->paper_height_mm} mm)" : 'Belum ada preset default aktif'
            );
            if (! $presetOk) {
                $failures++;
            }

            $defaultTemplate = DocumentTemplate::query()->where('is_default', true)->where('is_active', true)->first();
            $templateOk = $defaultTemplate !== null && $defaultTemplate->first_page_only === true;
            $this->renderStatus(
                'Template Kop Halaman Pertama (D-016)',
                $templateOk,
                $templateOk ? $defaultTemplate->name : 'Belum ada template default first_page_only'
            );
            if (! $templateOk) {
                $failures++;
            }

            $adminCount = User::query()->where('role', UserRole::ADMIN)->where('is_active', true)->count();
            $operatorCount = User::query()->where('role', UserRole::OPERATOR)->where('is_active', true)->count();
            $usersOk = ($adminCount + $operatorCount) > 0;
            $this->renderStatus(
                'Akun Pengguna Aktif',
                $usersOk,
                "Admin aktif: {$adminCount}, Operator aktif: {$operatorCount}"
            );
            if (! $usersOk) {
                $failures++;
            }
        }

        // 6. Folder Lokal Uji Pilot (fixtures_local)
        $fixturesDir = base_path('fixtures_local');
        if (! is_dir($fixturesDir)) {
            @mkdir($fixturesDir, 0775, true);
        }
        $docxFixtures = is_dir($fixturesDir) ? glob($fixturesDir . DIRECTORY_SEPARATOR . '*.docx') ?: [] : [];
        $this->components->twoColumnDetail(
            'Folder Naskah Pilot (fixtures_local)',
            is_dir($fixturesDir)
                ? count($docxFixtures) . ' berkas .docx siap diuji'
                : 'Direktori belum tersedia'
        );

        $this->newLine();

        if ($failures > 0) {
            $this->components->error("Ditemukan {$failures} prasyarat sistem yang belum siap. Periksa konfigurasi .env atau jalankan migrasi/seeder.");

            return self::FAILURE;
        }

        $this->components->info('Seluruh prasyarat sistem siap untuk menjalankan uji pilot dokumen soal.');

        return self::SUCCESS;
    }

    private function renderStatus(string $label, bool $ok, string $detail): void
    {
        $badge = $ok ? '<fg=green;options=bold>OK</> ' : '<fg=red;options=bold>GAGAL</> ';
        $this->components->twoColumnDetail($label, $badge . $detail);
    }

    private function checkDatabase(): bool
    {
        try {
            DB::connection()->getPdo();

            return Schema::hasTable('users')
                && Schema::hasTable('exam_documents')
                && Schema::hasTable('formatting_presets')
                && Schema::hasTable('document_templates');
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array{0: bool, 1: string}
     */
    private function checkPythonEngine(): array
    {
        $configuredPython = (string) config('simsoal.python_binary', '');
        $fallbackWindows = base_path('.venv' . DIRECTORY_SEPARATOR . 'Scripts' . DIRECTORY_SEPARATOR . 'python.exe');
        $fallbackUnix = base_path('.venv' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'python');

        $pythonBinary = match (true) {
            $configuredPython !== '' && is_file($configuredPython) => $configuredPython,
            is_file($fallbackWindows) => $fallbackWindows,
            is_file($fallbackUnix) => $fallbackUnix,
            default => '',
        };

        if ($pythonBinary === '') {
            return [false, 'Binary Python tidak ditemukan (periksa SIMSOAL_PYTHON_BINARY atau .venv)'];
        }

        $engineDir = base_path('engine');
        $hasEnginePackage = is_dir($engineDir . DIRECTORY_SEPARATOR . 'simsoal_docx');
        $enginePyFiles = is_dir($engineDir)
            ? array_merge(
                glob($engineDir . DIRECTORY_SEPARATOR . '*.py') ?: [],
                glob($engineDir . DIRECTORY_SEPARATOR . 'simsoal_docx' . DIRECTORY_SEPARATOR . '*.py') ?: []
            )
            : [];

        $configuredScript = (string) (
            config('simsoal.python_script')
            ?? config('simsoal.engine_script')
            ?? config('simsoal.docx_engine_script')
            ?? ''
        );

        $hasEngineCode = ($configuredScript !== '' && is_file($configuredScript))
            || count($enginePyFiles) > 0;

        if (! $hasEngineCode) {
            return [false, 'Skrip mesin Python DOCX tidak ditemukan di direktori engine/'];
        }

        try {
            $script = $hasEnginePackage
                ? 'import sys, docx, PIL, simsoal_docx; print(f"Python {sys.version.split()[0]} | python-docx {docx.__version__} | Pillow {PIL.__version__}")'
                : 'import sys, docx, PIL; print(f"Python {sys.version.split()[0]} | python-docx {docx.__version__} | Pillow {PIL.__version__}")';

            $process = Process::timeout(15);
            if (is_dir($engineDir)) {
                $process = $process->env(['PYTHONPATH' => $engineDir]);
            }

            $probe = $process->run([
                $pythonBinary,
                '-c',
                $script,
            ]);

            if (! $probe->successful()) {
                return [false, 'Modul simsoal_docx, python-docx, atau Pillow gagal dimuat di virtual environment'];
            }

            return [true, trim($probe->output())];
        } catch (Throwable $e) {
            return [false, 'Gagal menjalankan proses Python: ' . $e->getMessage()];
        }
    }

    /**
     * @return array{0: bool, 1: string}
     */
    private function checkPrivateStorage(): array
    {
        try {
            $probeFile = 'pilot-probes/check_' . uniqid('', true) . '.txt';
            Storage::disk('local')->put($probeFile, 'simsoal-ok');
            $content = Storage::disk('local')->get($probeFile);
            Storage::disk('local')->delete($probeFile);

            if ($content !== 'simsoal-ok') {
                return [false, 'Gagal memverifikasi baca/tulis pada disk privat local'];
            }

            return [true, 'Disk local privat dapat dibaca dan ditulis'];
        } catch (Throwable $e) {
            return [false, 'Kesalahan penyimpanan privat: ' . $e->getMessage()];
        }
    }
}