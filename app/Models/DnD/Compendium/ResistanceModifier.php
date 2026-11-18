<?php

namespace App\Models\DnD\Compendium;

use App\Models\DnD\Compendium\Interfaces\DndCompendiumModel;

/**
 * A modifier to the resistance or weakness to the given element
 * @inheritdoc
 * @property string element The element code for this modifier
 * @property int stage The amount to modify this resistance by
 * @property bool is_base Whether this sets the base resistance, or modifies it
 */
class ResistanceModifier extends DndCompendiumModel
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__compendium_resistance_modifier';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    /** @inheritdoc */
    protected $casts = [
        'stage' => 'integer',
        'is_base' => 'boolean',
    ];
}
