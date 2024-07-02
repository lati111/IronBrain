<?php

namespace App\Models\Compendium\Traits;

use App\Models\Compendium\Interfaces\AbstractTrait;

/**
 * @inheritDoc
 * @property string stat The stat to apply this trait to
 * @property string formula The formula to apply to the stat
 */

class StatTrait extends AbstractTrait
{
    /** { @inheritdoc } */
    protected $table = 'compendium__stat_trait';
}
