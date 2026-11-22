<?php

namespace App\Enums;

enum PaperCode: string
{
    case F4 = 'F4';
    case A4 = 'A4';
    case LETTER = 'LETTER';
    case CUSTOM = 'CUSTOM';

    public function label(): string
    {
        return match ($this) {
            self::F4 => 'F4 / Folio (215 × 330 mm)',
            self::A4 => 'A4 (210 × 297 mm)',
            self::LETTER => 'Letter (215.9 × 279.4 mm)',
            self::CUSTOM => 'Ukuran Kustom (mm)',
        };
    }

    /**
     * @return array{width_mm: float, height_mm: float}
     */
    public function defaultDimensions(): array
    {
        return match ($this) {
            self::F4 => ['width_mm' => 215.0, 'height_mm' => 330.0],
            self::A4 => ['width_mm' => 210.0, 'height_mm' => 297.0],
            self::LETTER => ['width_mm' => 215.9, 'height_mm' => 279.4],
            self::CUSTOM => ['width_mm' => 215.0, 'height_mm' => 330.0],
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