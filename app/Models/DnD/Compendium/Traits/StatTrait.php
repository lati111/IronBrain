<?php

namespace App\Models\DnD\Compendium\Traits;

use App\Models\DnD\Compendium\Interfaces\AbstractTrait;

/**
 * A trait that modifies a stat on a statblock
 * @inheritdoc
 * @property string stat The stat to apply this trait to
 * @property string formula The formula to apply to the stat
 */
class StatTrait extends AbstractTrait
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__compendium_stat_trait';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;
}
