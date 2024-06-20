<?php

namespace App\Models\PKSanc;

use App\Models\AbstractModel;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ribbon extends AbstractModel
{
    use HasFactory;
    use HasTimestamps;

    //TODO make on delete for sprite

    protected $table = 'pksanc__ribbon';
    protected $primaryKey = 'ribbon';
    public $incrementing = false;
}
