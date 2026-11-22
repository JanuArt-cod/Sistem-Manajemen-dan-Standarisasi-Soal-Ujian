<?php

namespace App\Services\DocumentProcessing;

use App\Exceptions\PdfConversionException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Throwable;

class LibreOfficePdfConverter
{
    public function detectVersion(): ?string
    {
        $binary = (string) config('simsoal.libreoffice_binary', '');
        if ($binary === '' || ! is_file($binary)) {
            return null;
        }

        $programDir = dirname($binary);

        // 1. Cek bootstrap.ini (mis. ProductKey=LibreOffice 24.8.x.x)
        $bootstrapIni = $programDir . DIRECTORY_SEPARATOR . 'bootstrap.ini';
        if (is_file($bootstrapIni)) {
            $content = (string) @file_get_contents($bootstrapIni);
            if (preg_match('/^ProductKey=(.+)$/m', $content, $matches) === 1) {
                $version = trim($matches[1]);
                if ($version !== '') {
                    return Str::limit($version, 80, '');
                }
            }
        }

        // 2. Cek version.ini (mis. MsiProductVersion=24.8.x.x)
        $versionIni = $programDir . DIRECTORY_SEPARATOR . 'version.ini';
        if (is_file($versionIni)) {
            $content = (string) @file_get_contents($versionIni);
            if (preg_match('/^MsiProductVersion=(.+)$/m', $content, $matches) === 1) {
                $version = trim($matches[1]);
                if ($version !== '') {
                    return Str::limit('LibreOffice ' . $version, 80, '');
                }
            }
        }

        // 3. Fallback ke soffice.com (Windows console wrapper) atau binary langsung (Linux/macOS)
        $consoleWrapper = $programDir . DIRECTORY_SEPARATOR . 'soffice.com';
        $versionBinary = is_file($consoleWrapper) ? $consoleWrapper : $binary;

        try {
            $result = Process::timeout(15)->run([$versionBinary, '--headless', '--version']);
            if (! $result->successful()) {
                return null;
            }

            $output = trim($result->output());
            if ($output === '') {
                return null;
            }

            $firstLine = strtok($output, "\r\n");

            return $firstLine !== false ? Str::limit(trim($firstLine), 80, '') : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Mengonversi berkas DOCX menjadi PDF di direktori kerja sementara dengan UserInstallation unik.
     *
     * @return array{pdf_path: string, libreoffice_version: string|null}
     */
    public function convertDocxToPdf(string $docxAbsPath, string $outputDirAbsPath): array
    {
        $binary = (string) config('simsoal.libreoffice_binary', '');

        if ($binary === '' || ! is_file($binary)) {
            throw new PdfConversionException(
                'LIBREOFFICE_NOT_FOUND',
                'Layanan LibreOffice tidak ditemukan di server sehingga ekspor PDF belum dapat dijalankan.'
            );
        }

        if (! is_file($docxAbsPath) || filesize($docxAbsPath) <= 0) {
            throw new PdfConversionException(
                'PDF_CONVERSION_FAILED',
                'Berkas DOCX sumber untuk konversi PDF tidak valid atau kosong.'
            );
        }

        $profileDir = $outputDirAbsPath . DIRECTORY_SEPARATOR . 'lo_profile_' . Str::random(10);
        if (! is_dir($profileDir) && ! mkdir($profileDir, 0775, true) && ! is_dir($profileDir)) {
            throw new PdfConversionException(
                'PDF_CONVERSION_FAILED',
                'Gagal menyiapkan profil sementara untuk konversi PDF.'
            );
        }

        $profileUri = $this->toFileUri($profileDir);
        $timeoutSeconds = max(30, (int) config('simsoal.process_timeout', 120));

        try {
            $result = Process::timeout($timeoutSeconds)->run([
                $binary,
                '--headless',
                '--nologo',
                '--nofirststartwizard',
                '--norestore',
                '-env:UserInstallation=' . $profileUri,
                '--convert-to',
                'pdf',
                '--outdir',
                $outputDirAbsPath,
                $docxAbsPath,
            ]);
        } catch (Throwable $e) {
            Log::error('SIMSOAL LibreOffice process exception', [
                'message' => $e->getMessage(),
            ]);

            throw new PdfConversionException(
                'ENGINE_TIMEOUT',
                'Konversi PDF melalui LibreOffice melebihi batas waktu atau terhenti.'
            );
        }

        if (! $result->successful()) {
            Log::error('SIMSOAL LibreOffice conversion returned non-zero exit code', [
                'exit_code' => $result->exitCode(),
                'stderr' => Str::limit($result->errorOutput(), 500),
                'stdout' => Str::limit($result->output(), 500),
            ]);

            throw new PdfConversionException(
                'PDF_CONVERSION_FAILED',
                'LibreOffice gagal mengonversi dokumen DOCX menjadi PDF.'
            );
        }

        $stem = pathinfo($docxAbsPath, PATHINFO_FILENAME);
        $expectedPdfPath = $outputDirAbsPath . DIRECTORY_SEPARATOR . $stem . '.pdf';

        if (! is_file($expectedPdfPath) || filesize($expectedPdfPath) <= 0) {
            throw new PdfConversionException(
                'OUTPUT_VALIDATION_FAILED',
                'Berkas keluaran PDF tidak terbentuk setelah proses konversi selesai.'
            );
        }

        $handle = @fopen($expectedPdfPath, 'rb');
        $magicBytes = $handle !== false ? (string) fread($handle, 5) : '';
        if ($handle !== false) {
            fclose($handle);
        }

        if (! str_starts_with($magicBytes, '%PDF-')) {
            throw new PdfConversionException(
                'OUTPUT_VALIDATION_FAILED',
                'Berkas keluaran PDF tidak memiliki struktur dokumen PDF yang valid.'
            );
        }

        return [
            'pdf_path' => $expectedPdfPath,
            'libreoffice_version' => $this->detectVersion(),
        ];
    }

    private function toFileUri(string $directoryPath): string
    {
        $normalized = str_replace('\\', '/', $directoryPath);

        if (preg_match('/^[A-Za-z]:\//', $normalized) === 1) {
            return 'file:///' . $normalized;
        }

        return 'file://' . $normalized;
    }
}