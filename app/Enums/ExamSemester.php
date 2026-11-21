<?php

namespace App\Enums;

enum ExamSemester: string
{
    case ODD = 'ODD';
    case EVEN = 'EVEN';
    case CUSTOM = 'CUSTOM';

    public function label(): string
    {
        return match ($this) {
            self::ODD => 'Ganjil',
            self::EVEN => 'Genap',
            self::CUSTOM => 'Khusus / Lainnya',
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