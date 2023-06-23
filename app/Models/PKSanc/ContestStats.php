<?php

namespace App\Models\PKSanc;

use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContestStats extends Model
{
    use HasFactory;
    use HasTimestamps;

    protected $table = 'pksanc__contest_stats';
    protected $primaryKey = 'pokemon_uuid';
}
