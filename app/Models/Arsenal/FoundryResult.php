<?php

namespace App\Models\Arsenal;

use Illuminate\Database\Eloquent\Model;

/**
 * Ephemeral model used as an Eloquent Builder bridge for the foundry grouped query.
 * The actual FROM clause is replaced by fromSub() — $table is never queried.
 */
class FoundryResult extends Model
{
    protected $table = 'foundry_result';
    protected $primaryKey = null;
    public $incrementing = false;
    public $timestamps = false;
}
