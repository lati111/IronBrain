<?php

namespace App\Models\PKSanc;

use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ribbon extends Model
{
    use HasFactory;
    use HasTimestamps;

    //TODO make on delete for sprite

    protected $table = 'pksanc__ribbon';
    protected $primaryKey = 'ribbon';
    public $incrementing = false;
}
