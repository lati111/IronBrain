<?php

namespace App\Models\Compendium;

use App\Models\AbstractModel;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @inheritDoc
 * @property string uuid
 * @property string ability The code for this ability
 * @property string name The display for this statblock
 * @property string|null alignment The alignment for the creature, if any
 * @property int base_ac The base AC for this creature
 * @property int base_hp The base HP value for this creature
 * @property int temp_hp The temp HP value for this creature
 * @property int base_strength The base strength stat for this creature
 * @property int base_dexterity The base dexterity stat for this creature
 * @property int base_constitution The base constitution stat for this creature
 * @property int base_intelligence The base intelligence stat for this creature
 * @property int base_wisdom The base wisdom stat this for creature
 * @property int base_charisma The base charisma stat for this creature
 * @property int base_walk_speed The base walk speed for this creature
 * @property int base_swim_speed The base swim speed for this creature
 * @property int base_climb_speed The base climb speed for this creature
 * @property int base_fly_speed The base fly speed for this creature
 * @property int base_burrow_speed The base burrow speed for this creature
 * @property string created_at The creation date of the model as a string
 * @property string updated_at The date this model was last updated
 */

class Statblock extends AbstractModel
{
    use HasUuids, HasTimestamps;

    /** { @inheritdoc } */
    protected $table = 'compendium__statblock';

    /** { @inheritdoc } */
    protected $primaryKey = 'uuid';

    /**
     * Add a creature template to this statblock
     * @param CreatureTemplate $template The template to add
     * @return StatblockCreatureTemplate The created link table
     */
    public function addCreatureTemplate(CreatureTemplate $template): StatblockCreatureTemplate
    {
        $link = new StatblockCreatureTemplate();
        $link->statblock_uuid = $this->uuid;
        $link->creature_template = $template->code;
        $link->save();

        return $link;
    }

    /**
     * The hasMany relationship to include the resistances in the query
     * @return HasMany
     */
    public function resistances(): HasMany {
        return $this->hasMany(StatblockCreatureTemplate::class, 'statblock_uuid', 'uuid')
            ->select(sprintf('%s.element', ResistanceModifier::getTableName()))
            ->jointable(CreatureTemplate::getTableName(), StatblockCreatureTemplate::getTableName(), 'creature_template', '=', 'code')
            ->jointable(CreatureTemplateResistance::getTableName(), CreatureTemplate::getTableName(), 'code', '=', 'creature_template')
            ->jointable(ResistanceModifier::getTableName(), CreatureTemplateResistance::getTableName(), 'resistance_modifier_uuid', '=', 'uuid')
            ->orderBy(sprintf('%s.is_base', ResistanceModifier::getTableName()))
            ->orderBy(sprintf('%s.stage', ResistanceModifier::getTableName()), 'desc')
            ->select([
                sprintf('%s.statblock_uuid', StatblockCreatureTemplate::getTableName()),
                sprintf('%s.name as source', CreatureTemplate::getTableName()),
                sprintf('%s.element', ResistanceModifier::getTableName()),
                sprintf('%s.stage', ResistanceModifier::getTableName()),
                sprintf('%s.is_base', ResistanceModifier::getTableName()),
            ]);
    }
}
