<?php

namespace App\Models\PKSanc;

use App\Models\AbstractModel;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string pokeball The code for this pokeball
 * @property string name The display name for this pokeball
 * @property string sprite The sprite belonging to this pokeball
 * @property string created_at The creation date of the model as a string
 * @property string updated_at The date this model was last updated
 */

class Pokeball extends AbstractModel
{
    use HasTimestamps;

    //TODO make on delete for sprite

    protected $table = 'pksanc__pokeball';
    protected $primaryKey = 'pokeball';
    public $incrementing = false;
}
