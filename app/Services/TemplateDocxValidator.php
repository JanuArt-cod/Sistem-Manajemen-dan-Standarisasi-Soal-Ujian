<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use ZipArchive;

class TemplateDocxValidator
{
    private const MAX_BYTES = 10485760; // 10 MB

    /**
     * Memvalidasi signature ZIP dan struktur internal OOXML dari file .docx template kop.
     *
     * @return array{valid: bool, error: ?string, checksum: ?string}
     */
    public function validate(UploadedFile $file): array
    {
        $realPath = $file->getRealPath();

        if ($realPath === false) {
            return [
                'valid' => false,
                'error' => 'File template tidak dapat dibaca oleh server.',
                'checksum' => null,
            ];
        }

        if (! is_file($realPath) || ! is_readable($realPath)) {
            return [
                'valid' => false,
                'error' => 'File template tidak ditemukan atau tidak memiliki izin baca.',
                'checksum' => null,
            ];
        }

        $size = filesize($realPath);
        if ($size === false || $size <= 0) {
            return [
                'valid' => false,
                'error' => 'File template .docx kosong (0 byte).',
                'checksum' => null,
            ];
        }

        if ($size > self::MAX_BYTES) {
            return [
                'valid' => false,
                'error' => 'Ukuran file template .docx maksimal 10 MB.',
                'checksum' => null,
            ];
        }

        $handle = fopen($realPath, 'rb');
        $header = $handle !== false ? fread($handle, 4) : false;
        if ($handle !== false) {
            fclose($handle);
        }

        if ($header !== "PK\x03\x04") {
            return [
                'valid' => false,
                'error' => 'Signature file tidak valid. Template kop khusus wajib berupa dokumen Microsoft Word (.docx) asli.',
                'checksum' => null,
            ];
        }

        $zip = new ZipArchive();
        if ($zip->open($realPath) !== true) {
            return [
                'valid' => false,
                'error' => 'Arsip OOXML pada file template .docx rusak atau tidak dapat dibuka.',
                'checksum' => null,
            ];
        }

        $requiredEntries = ['[Content_Types].xml', '_rels/.rels', 'word/document.xml'];
        foreach ($requiredEntries as $entry) {
            if ($zip->locateName($entry) === false) {
                $zip->close();

                return [
                    'valid' => false,
                    'error' => "Struktur dokumen Word tidak lengkap (komponen {$entry} tidak ditemukan).",
                    'checksum' => null,
                ];
            }
        }

        $documentXml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($documentXml === false) {
            return [
                'valid' => false,
                'error' => 'Gagal membaca word/document.xml pada template kop.',
                'checksum' => null,
            ];
        }

        if (trim($documentXml) === '') {
            return [
                'valid' => false,
                'error' => 'Isi word/document.xml pada template kop kosong.',
                'checksum' => null,
            ];
        }

        $previousXmlErrorState = libxml_use_internal_errors(true);
        $parsedXml = simplexml_load_string($documentXml, 'SimpleXMLElement', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previousXmlErrorState);

        if ($parsedXml === false) {
            return [
                'valid' => false,
                'error' => 'XML internal pada file template kop .docx tidak valid.',
                'checksum' => null,
            ];
        }

        $checksum = hash_file('sha256', $realPath);

        return [
            'valid' => true,
            'error' => null,
            'checksum' => $checksum !== false ? $checksum : null,
        ];
    }
}