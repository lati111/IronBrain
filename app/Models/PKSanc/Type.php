<?php

namespace App\Models\PKSanc;

use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Type extends Model
{
    use HasFactory;
    use HasTimestamps;

    protected $table = 'pksanc__type';
    protected $primaryKey = 'type';
    public $incrementing = false;
}
