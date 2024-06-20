<?php

namespace App\Models\PKSanc;

use App\Models\AbstractModel;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string type The code for this type
 * @property string name The display name for this type
 * @property string created_at The creation date of the model as a string
 * @property string updated_at The date this model was last updated
 */

class Type extends AbstractModel
{
    use HasTimestamps;

    protected $table = 'pksanc__type';
    protected $primaryKey = 'type';
    public $incrementing = false;
}
