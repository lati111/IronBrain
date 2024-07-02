<?php

namespace App\Models\Compendium\Traits;

use App\Models\Compendium\Interfaces\AbstractTrait;

/**
 * @inheritDoc
 * @property string action_uuid The uuid of the action to add to the trait
 */

class ActionTrait extends AbstractTrait
{
    /** { @inheritdoc } */
    protected $table = 'compendium__action_trait';
}
