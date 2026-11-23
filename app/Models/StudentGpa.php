<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentGpa extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['can_transfer' => 'boolean'];
    }
}
