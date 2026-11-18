<?php

namespace App\Models\DnD\Compendium;

use App\Models\DnD\Compendium\Interfaces\DndCompendiumModel;

/**
 * A resource pool for a character
 * @inheritdoc
 * @property string code The internal code used to identify the resource pool
 * @property string name The display name for this resource
 * @property int recharge_interval When the resource recharges. 0=not, 1=short rest, 2=long rest, 3=dawn
 * @property string|null recharge_formula The formula used to calculate how much is recharged. When null recharges all
 */
class Resource extends DndCompendiumModel
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__compendium_resource';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    /** @inheritdoc */
    protected $casts = [
        'recharge_interval' => 'integer',
    ];
}
