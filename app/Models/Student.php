<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Reyestrdagi shaxs (talaba / sobiq talaba) — HEMIS student-list yozuvi.
 */
class Student extends Model
{
    public const STATUS_STUDYING = '11';

    public const STATUS_EXPELLED = '12';

    public const STATUS_ACADEMIC_LEAVE = '13';

    public const STATUS_GRADUATED = '14';

    public const STATUS_REPEATING = '15';

    public const STATUS_MOBILITY = '16';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'is_graduate' => 'boolean',
            'avg_gpa' => 'decimal:2',
            'hemis_created_at' => 'datetime',
            'hemis_updated_at' => 'datetime',
            'raw' => 'array',
        ];
    }
}
