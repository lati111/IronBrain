<?php

namespace App\Models\Compendium\Traits;

use App\Models\Compendium\Interfaces\AbstractTrait;

/**
 * @inheritDoc
 * @property string resource_uuid The uuid of the resource to add to the trait
 * @property string cap_formula The formula that determines the resource cap
 */

class ResourceTrait extends AbstractTrait
{
    /** { @inheritdoc } */
    protected $table = 'compendium__resource_trait';
}
