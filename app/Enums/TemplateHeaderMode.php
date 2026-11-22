<?php

namespace App\Enums;

enum TemplateHeaderMode: string
{
    case STANDARD_SCHOOL = 'STANDARD_SCHOOL';
    case CUSTOM_DOCX = 'CUSTOM_DOCX';
    case NONE = 'NONE';

    public function label(): string
    {
        return match ($this) {
            self::STANDARD_SCHOOL => 'Kop Standar Profil Sekolah (Halaman 1)',
            self::CUSTOM_DOCX => 'Kop Khusus dari File .docx (Halaman 1)',
            self::NONE => 'Tanpa Penambahan Kop (Pertahankan Asli)',
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