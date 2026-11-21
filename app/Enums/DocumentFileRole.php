<?php

namespace App\Enums;

enum DocumentFileRole: string
{
    case SOURCE = 'SOURCE';
    case NORMALIZED_SOURCE = 'NORMALIZED_SOURCE';
    case OUTPUT_DOCX = 'OUTPUT_DOCX';
    case OUTPUT_PDF = 'OUTPUT_PDF';
    case PREVIEW = 'PREVIEW';
    case OTHER = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::SOURCE => 'Dokumen Sumber Asli',
            self::NORMALIZED_SOURCE => 'Sumber Ternormalisasi',
            self::OUTPUT_DOCX => 'Hasil Standarisasi DOCX',
            self::OUTPUT_PDF => 'Hasil Cetak PDF',
            self::PREVIEW => 'Pratinjau',
            self::OTHER => 'Lainnya',
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