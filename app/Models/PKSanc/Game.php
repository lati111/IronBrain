<?php

namespace App\Models\PKSanc;

use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string game The code for this game as used in PKHeX
 * @property string name The display name for this game
 * @property ?string original_game which game this game is based of, in the case of romhacks. Otherwise null
 * @property string created_at The creation date of the model as a string
 * @property string updated_at The date this model was last updated
 */

class Game extends Model
{
    use HasTimestamps;

    protected $table = 'pksanc__game';
    protected $primaryKey = 'game';
    public $incrementing = false;
}
