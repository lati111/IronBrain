<?php

namespace App\Models\DnD\Compendium;

use App\Models\DnD\Compendium\Interfaces\DndCompendiumModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Linking table between a statblock and a creature template
 * @inheritdoc
 * @property string statblock_uuid The uuid of the statblock the creature template applies to
 * @property string creature_template The code of the creature template to apply
 */
class StatblockCreatureTemplate extends DndCompendiumModel
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__compendium_statblock_creature_template';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    //| Relationships

    /**
     * The relationship for the statblock the creature template applies to
     * @return BelongsTo
     */
    public function Statblock(): BelongsTo {
        return $this->belongsTo(Statblock::class, 'statblock_uuid', 'uuid');
    }

    /**
     * The relationship for the creature template that is applied
     * @return BelongsTo
     */
    public function CreatureTemplate(): BelongsTo {
        return $this->belongsTo(CreatureTemplate::class, 'creature_template', 'code');
    }
}
