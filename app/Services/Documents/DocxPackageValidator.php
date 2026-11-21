<?php

namespace App\Services\Documents;

use Illuminate\Http\UploadedFile;
use ZipArchive;

class DocxPackageValidator
{
    /**
     * Validasi keaslian struktur paket DOCX/OOXML sebelum disimpan.
     *
     * @return array{valid: bool, error: ?string, mime_type: string, size_bytes: int, checksum_sha256: string}
     */
    public function validateUploadedDocx(UploadedFile $file): array
    {
        $realPath = $file->getRealPath();
        $sizeBytes = (int) $file->getSize();
        $maxBytes = (int) config('simsoal.max_upload_mb', 30) * 1024 * 1024;

        if (! $realPath || ! is_file($realPath) || $sizeBytes <= 0) {
            return $this->fail('File unggahan kosong atau tidak dapat dibaca.');
        }

        if ($sizeBytes > $maxBytes) {
            $maxMb = config('simsoal.max_upload_mb', 30);

            return $this->fail("Ukuran file melebihi batas maksimal {$maxMb} MB.");
        }

        $clientExtension = strtolower($file->getClientOriginalExtension());
        if ($clientExtension !== 'docx') {
            return $this->fail('Versi MVP hanya mendukung format .docx. Konversi file .doc/.pdf ke .docx terlebih dahulu.');
        }

        $detectedMime = (string) ($file->getMimeType() ?: 'application/octet-stream');
        $allowedMimes = [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
            'application/x-zip-compressed',
            'application/octet-stream',
        ];

        if (! in_array($detectedMime, $allowedMimes, true)) {
            return $this->fail('Tipe konten file tidak sesuai dengan dokumen Microsoft Word (.docx).');
        }

        $zip = new ZipArchive();
        $openResult = $zip->open($realPath, ZipArchive::RDONLY);

        if ($openResult !== true) {
            return $this->fail('File bukan paket arsip DOCX/OOXML yang valid.');
        }

        $hasContentTypes = false;
        $hasMainDocument = false;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = $zip->getNameIndex($i);
            if ($entryName === false) {
                continue;
            }

            if (str_contains($entryName, '..')) {
                $zip->close();

                return $this->fail('Struktur arsip DOCX mengandung jalur tidak aman.');
            }

            if ($entryName === '[Content_Types].xml') {
                $hasContentTypes = true;
            }

            if ($entryName === 'word/document.xml') {
                $hasMainDocument = true;
            }
        }

        $zip->close();

        if (! $hasContentTypes || ! $hasMainDocument) {
            return $this->fail('Arsip ZIP tidak memiliki struktur inti dokumen Word (word/document.xml).');
        }

        $checksum = hash_file('sha256', $realPath);
        if ($checksum === false) {
            return $this->fail('Gagal menghitung checksum integritas file sumber.');
        }

        return [
            'valid' => true,
            'error' => null,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'size_bytes' => $sizeBytes,
            'checksum_sha256' => $checksum,
        ];
    }

    /**
     * @return array{valid: bool, error: string, mime_type: string, size_bytes: int, checksum_sha256: string}
     */
    private function fail(string $message): array
    {
        return [
            'valid' => false,
            'error' => $message,
            'mime_type' => '',
            'size_bytes' => 0,
            'checksum_sha256' => '',
        ];
    }
}