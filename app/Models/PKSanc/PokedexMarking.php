<?php

namespace App\Models\PKSanc;

use App\Models\AbstractModel;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @inheritdoc
 * @property string uuid
 * @property int pokedex_id The code for this ability
 * @property int form_index The display name for this ability
 * @property string marking The marking to use for the pokedex entry. Comes from PokedexMarkings enum.
 * @property string user_uuid The uuid of the user that marked this pokemon as read
 * @property string created_at The creation date of the model as a string
 * @property string updated_at The date this model was last updated
 */

class PokedexMarking extends AbstractModel
{
    use HasUuids, HasTimestamps;

    /** { @inheritdoc } */
    protected $table = 'pksanc__pokedex_marking';

    /** { @inheritdoc } */
    protected $primaryKey = 'uuid';
}
