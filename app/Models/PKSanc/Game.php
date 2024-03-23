<?php

namespace App\Models\PKSanc;

use App\Models\AbstractModel;

/**
 * @inheritDoc
 * @property string game The code for this game as used in PKHeX
 * @property string name The display name for this game
 * @property ?string original_game which game this game is based of, in the case of romhacks. Otherwise null
 * @property boolean is_romhack Whether this game is a romhack or not
 */

class Game extends AbstractModel
{
    /** { @inheritdoc } */
    protected $table = 'pksanc__game';

    /** { @inheritdoc } */
    protected $primaryKey = 'game';

    /** { @inheritdoc } */
    protected $keyType = 'string';

    /** { @inheritdoc } */
    protected $casts = [
        'is_romhack' => 'boolean',
    ];
}
