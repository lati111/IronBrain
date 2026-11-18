<?php

namespace App\Models\DnD\Compendium;

use App\Models\AbstractModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Creature templates that modify a statblock automatically
 * @inheritdoc
 * @property string code The code for this creature template
 * @property string name The display name for this creature template
 * @property string created_at The creation date of the model as a string
 * @property string updated_at The date this model was last updated
 */
class CreatureTemplate extends AbstractModel
{
    /** @var string The name of the table */
    public const string TABLE_NAME = 'dnd__compendium_creature_template';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    /** @inheritdoc */
    protected $primaryKey = 'code';

    /** @inheritdoc */
    protected $keyType = 'string';

    /** @inheritdoc */
    public $incrementing = false;

    //| Relationships

    /**
     * The relationship for the resistance modifier links of this template
     * @return HasMany
     */
    public function ResistanceModifiers(): HasMany {
        return $this->hasMany(CreatureTemplateResistance::class, 'creature_template', 'code');
    }

    //| Actions

    /**
     * Add a resistance modifier to this creature template
     * @param ResistanceModifier $modifier The modifier to add
     * @return CreatureTemplateResistance The created link table
     */
    public function addResistanceModifier(ResistanceModifier $modifier): CreatureTemplateResistance
    {
        $link = new CreatureTemplateResistance();
        $link->creature_template = $this->code;
        $link->resistance_modifier_uuid = $modifier->uuid;
        $link->save();

        return $link;
    }
}
