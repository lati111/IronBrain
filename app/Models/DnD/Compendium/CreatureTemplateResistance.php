<?php

namespace App\Models\DnD\Compendium;

use App\Models\DnD\Compendium\Interfaces\DndCompendiumModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A link between a creature template and a resistance modifier it applies
 * @inheritdoc
 * @property string creature_template The code of the creature template to apply the resistance modifier to
 * @property string resistance_modifier_uuid The uuid of the resistance modifier to add
 */
class CreatureTemplateResistance extends DndCompendiumModel
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__compendium_creature_template_resistance_modifier';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    //| Relationships

    /**
     * The relationship for the creature template this link belongs to
     * @return BelongsTo
     */
    public function CreatureTemplate(): BelongsTo {
        return $this->belongsTo(CreatureTemplate::class, 'creature_template', 'code');
    }

    /**
     * The relationship for the resistance modifier that is applied
     * @return BelongsTo
     */
    public function ResistanceModifier(): BelongsTo {
        return $this->belongsTo(ResistanceModifier::class, 'resistance_modifier_uuid', 'uuid');
    }
}
