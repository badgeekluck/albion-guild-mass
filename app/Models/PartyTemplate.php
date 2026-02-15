<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartyTemplate extends Model
{
    protected $guarded = [];

    protected $casts = [
        'structure' => 'array',
    ];
}
