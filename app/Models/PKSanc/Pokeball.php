<?php

namespace App\Models\PKSanc;

use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pokeball extends Model
{
    use HasFactory;
    use HasTimestamps;

    //TODO make on delete for sprite

    protected $table = 'pksanc__pokeball';
    protected $primaryKey = 'pokeball';
    public $incrementing = false;
}
