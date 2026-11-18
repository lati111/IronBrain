<?php

namespace App\Models\Compendium\Traits;

use App\Models\Compendium\Interfaces\AbstractTrait;

/**
 * @inheritDoc
 * @property string roll_type The roll type to apply this trait to
 * @property string formula The formula to add to the roll
 */

class RollModifierTrait extends AbstractTrait
{
    /** { @inheritdoc } */
    protected $table = 'compendium__roll_modifier_trait';
}
