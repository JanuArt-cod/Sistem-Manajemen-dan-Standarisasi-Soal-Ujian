<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolProfile extends Model
{
    protected $fillable = [
        'institution_name',
        'foundation_name',
        'address',
        'phone',
        'email',
        'logo_path',
        'header_lines',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'header_lines' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public static function current(): ?self
    {
        return static::query()
            ->where('is_active', true)
            ->latest('id')
            ->first();
    }
}