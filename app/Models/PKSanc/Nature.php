<?php

namespace App\Models\PKSanc;

use App\Models\AbstractModel;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string nature The code for this nature
 * @property string name The display name for this nature
 * @property float atk_modifier The multiplier for the attack stat, 1 at default
 * @property float def_modifier The multiplier for the defense stat, 1 at default
 * @property float spa_modifier The multiplier for the special attack stat, 1 at default
 * @property float spd_modifier The multiplier for the special defense stat, 1 at default
 * @property float spe_modifier The multiplier for the speed stat, 1 at default
 * @property string created_at The creation date of the model as a string
 * @property string updated_at The date this model was last updated
 */

class Nature extends AbstractModel
{
    use HasTimestamps;

    protected $table = 'pksanc__nature';
    protected $primaryKey = 'nature';
    public $incrementing = false;
}
