<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LetterCounter extends Model
{
    protected $fillable = [
        'year',
        'last_sequence',
    ];
}

