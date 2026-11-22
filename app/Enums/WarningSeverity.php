<?php

namespace App\Enums;

enum WarningSeverity: string
{
    case INFO = 'INFO';
    case WARNING = 'WARNING';
    case REVIEW_REQUIRED = 'REVIEW_REQUIRED';

    public function label(): string
    {
        return match ($this) {
            self::INFO => 'Informasi',
            self::WARNING => 'Peringatan',
            self::REVIEW_REQUIRED => 'Perlu Tinjauan Manual',
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