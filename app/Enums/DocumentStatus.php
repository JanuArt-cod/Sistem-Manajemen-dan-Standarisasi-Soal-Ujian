<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case UPLOADED = 'UPLOADED';
    case VALIDATION_FAILED = 'VALIDATION_FAILED';
    case QUEUED = 'QUEUED';
    case PROCESSING = 'PROCESSING';
    case PROCESSED = 'PROCESSED';
    case PDF_FAILED = 'PDF_FAILED';
    case NEEDS_REVIEW = 'NEEDS_REVIEW';
    case APPROVED = 'APPROVED';
    case REVISION_REQUIRED = 'REVISION_REQUIRED';
    case FAILED = 'FAILED';
    case ARCHIVED = 'ARCHIVED';

    public function label(): string
    {
        return match ($this) {
            self::UPLOADED => 'Diunggah',
            self::VALIDATION_FAILED => 'Validasi Gagal',
            self::QUEUED => 'Dalam Antrean',
            self::PROCESSING => 'Sedang Diproses',
            self::PROCESSED => 'DOCX Selesai',
            self::PDF_FAILED => 'PDF Gagal',
            self::NEEDS_REVIEW => 'Perlu Pemeriksaan',
            self::APPROVED => 'Disetujui',
            self::REVISION_REQUIRED => 'Perlu Revisi',
            self::FAILED => 'Proses Gagal',
            self::ARCHIVED => 'Diarsipkan',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}