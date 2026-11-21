<?php

namespace App\Enums;

enum DocumentSourceFormat: string
{
    case DOCX = 'DOCX';
    case DOC = 'DOC';
    case PDF = 'PDF';

    public function label(): string
    {
        return match ($this) {
            self::DOCX => 'Microsoft Word (.docx)',
            self::DOC => 'Word Lama (.doc)',
            self::PDF => 'Dokumen PDF (.pdf)',
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