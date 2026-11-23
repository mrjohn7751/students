<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Diploma extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'register_date' => 'date',
            'enroll_date' => 'date',
            'graduate_date' => 'date',
            'accepted' => 'boolean',
            'published' => 'boolean',
            'raw' => 'array',
        ];
    }
}
