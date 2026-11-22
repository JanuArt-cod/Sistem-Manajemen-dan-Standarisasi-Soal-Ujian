<?php

namespace App\Enums;

enum ProcessingRunStatus: string
{
    case QUEUED = 'QUEUED';
    case PROCESSING = 'PROCESSING';
    case COMPLETED = 'COMPLETED';
    case COMPLETED_WITH_WARNINGS = 'COMPLETED_WITH_WARNINGS';
    case FAILED = 'FAILED';

    public function label(): string
    {
        return match ($this) {
            self::QUEUED => 'Dalam Antrean',
            self::PROCESSING => 'Sedang Diproses',
            self::COMPLETED => 'Selesai Tanpa Peringatan',
            self::COMPLETED_WITH_WARNINGS => 'Selesai dengan Peringatan',
            self::FAILED => 'Gagal Diproses',
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