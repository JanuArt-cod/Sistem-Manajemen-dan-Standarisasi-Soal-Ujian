<?php

namespace App\Enums;

enum PaperOrientation: string
{
    case PORTRAIT = 'PORTRAIT';
    case LANDSCAPE = 'LANDSCAPE';

    public function label(): string
    {
        return match ($this) {
            self::PORTRAIT => 'Potret (Portrait)',
            self::LANDSCAPE => 'Lanskap (Landscape)',
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