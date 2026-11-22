<?php

namespace App\Enums;

enum PaperSize: string
{
    case F4 = 'F4';
    case A4 = 'A4';
    case LEGAL = 'LEGAL';
    case LETTER = 'LETTER';
    case CUSTOM = 'CUSTOM';

    public function label(): string
    {
        return match ($this) {
            self::F4 => 'F4 / Folio (215 × 330 mm)',
            self::A4 => 'A4 (210 × 297 mm)',
            self::LEGAL => 'Legal (215,9 × 355,6 mm)',
            self::LETTER => 'Letter (215,9 × 279,4 mm)',
            self::CUSTOM => 'Ukuran Kustom (mm)',
        };
    }

    /**
     * @return array{width_mm: float, height_mm: float}
     */
    public function defaultDimensionsMm(): array
    {
        return match ($this) {
            self::F4 => ['width_mm' => 215.00, 'height_mm' => 330.00],
            self::A4 => ['width_mm' => 210.00, 'height_mm' => 297.00],
            self::LEGAL => ['width_mm' => 215.90, 'height_mm' => 355.60],
            self::LETTER => ['width_mm' => 215.90, 'height_mm' => 279.40],
            self::CUSTOM => ['width_mm' => 215.00, 'height_mm' => 330.00],
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