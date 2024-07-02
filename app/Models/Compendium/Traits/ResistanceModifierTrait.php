<?php

namespace App\Models\Compendium\Traits;

use App\Models\Compendium\Interfaces\AbstractTrait;

/**
 * @inheritDoc
 * @property string resistance_modifier_uuid The uuid of the resistance modifier to add to the trait
 */

class ResistanceModifierTrait extends AbstractTrait
{
    /** { @inheritdoc } */
    protected $table = 'compendium__resistance_modifier_trait';
}
