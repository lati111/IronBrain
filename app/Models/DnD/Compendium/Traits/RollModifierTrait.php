<?php

namespace App\Models\DnD\Compendium\Traits;

use App\Models\DnD\Compendium\Interfaces\AbstractTrait;

/**
 * A trait that modifies a roll made by the statblock
 * @inheritdoc
 * @property string roll_type The roll type to apply this trait to
 * @property string formula The formula to add to the roll
 */
class RollModifierTrait extends AbstractTrait
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__compendium_roll_modifier_trait';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;
}
