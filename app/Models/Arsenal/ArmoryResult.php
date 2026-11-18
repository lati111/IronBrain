<?php

namespace App\Models\Arsenal;

use Illuminate\Database\Eloquent\Model;

/**
 * Ephemeral model used as an Eloquent Builder bridge for the armory union query.
 * The actual FROM clause is replaced by fromSub() — $table is never queried.
 */
class ArmoryResult extends Model
{
    protected $table = 'armory_result';
    protected $primaryKey = null;
    public $incrementing = false;
    public $timestamps = false;
}
