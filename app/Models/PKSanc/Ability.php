<?php

namespace App\Models\PKSanc;

use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string ability The code for this ability
 * @property string name The display name for this ability
 * @property string description The description belonging to this ability
 * @property string created_at The creation date of the model as a string
 * @property string updated_at The date this model was last updated
 */

class Ability extends Model
{
    use HasTimestamps;

    protected $table = 'pksanc__ability';
    protected $primaryKey = 'ability';
    public $incrementing = false;
}
