<?php

namespace App\Models\Compendium;

use App\Models\AbstractModel;
use App\Models\Compendium\Traits\ActionTrait;
use App\Models\Compendium\Traits\CompendiumTrait;
use App\Models\Compendium\Traits\ProficiencyTrait;
use App\Models\Compendium\Traits\ResistanceModifierTrait;
use App\Models\Compendium\Traits\ResourceTrait;
use App\Models\Compendium\Traits\RollModifierTrait;
use App\Models\Compendium\Traits\StatTrait;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @inheritDoc
 * @property string uuid
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
     * Add a trait to this statblock
     * @param CompendiumTrait $trait The trait to add
     * @return StatblockTrait The created link table
     */
    public function addTrait(CompendiumTrait $trait): StatblockTrait
    {
        $link = new StatblockTrait();
        $link->statblock_uuid = $this->uuid;
        $link->trait_uuid = $trait->uuid;
        $link->save();

        return $link;
    }

    /**
     * The hasMany relationship to include the roll modifiers in the query
     * @return HasMany
     */
    public function actions(): HasMany
    {
        return
            $this->unconstrainedHasMany(Action::class, StatblockTrait::getTableName(), 'statblock_uuid', 'uuid')
            ->from(Action::getTableName())
            ->jointable(ActionTrait::getTableName(), Action::getTableName(), 'uuid', '=', 'action_uuid')
            ->jointable(CompendiumTrait::getTableName(), ActionTrait::getTableName(), 'trait_uuid', '=', 'uuid')
            ->jointable(StatblockTrait::getTableName(), CompendiumTrait::getTableName(), 'uuid', '=', 'trait_uuid')
            ->with('costs')
            ->select([
                sprintf('%s.uuid', Action::getTableName()),
                sprintf('%s.statblock_uuid', StatblockTrait::getTableName()),
                sprintf('%s.trait_uuid as source_uuid', StatblockTrait::getTableName()),
                sprintf('%s.name as source', CompendiumTrait::getTableName()),
                sprintf('%s.uuid as action_uuid', Action::getTableName()),
                sprintf('%s.name', Action::getTableName()),
                sprintf('%s.type', Action::getTableName()),
                sprintf('%s.description', Action::getTableName()),
            ]);
    }

    /**
     * The hasMany relationship to include the resistances in the query
     * @return HasMany
     */
    public function resistances(): HasMany {
        $creatureTemplateResistances = $this->hasMany(StatblockCreatureTemplate::class, 'statblock_uuid', 'uuid')
            ->select(sprintf('%s.element', ResistanceModifier::getTableName()))
            ->jointable(CreatureTemplate::getTableName(), StatblockCreatureTemplate::getTableName(), 'creature_template', '=', 'code')
            ->jointable(CreatureTemplateResistance::getTableName(), CreatureTemplate::getTableName(), 'code', '=', 'creature_template')
            ->jointable(ResistanceModifier::getTableName(), CreatureTemplateResistance::getTableName(), 'resistance_modifier_uuid', '=', 'uuid')
            ->orderBy(sprintf('%s.is_base', ResistanceModifier::getTableName()))
            ->orderBy(sprintf('%s.stage', ResistanceModifier::getTableName()), 'desc')
            ->select([
                sprintf('%s.statblock_uuid', StatblockCreatureTemplate::getTableName()),
                sprintf('%s.creature_template as source_uuid', StatblockCreatureTemplate::getTableName()),
                sprintf('%s.name as source', CreatureTemplate::getTableName()),
                sprintf('%s.element', ResistanceModifier::getTableName()),
                sprintf('%s.stage', ResistanceModifier::getTableName()),
                sprintf('%s.is_base', ResistanceModifier::getTableName()),
            ]);

        $traitResistances = $this->hasMany(StatblockTrait::class, 'statblock_uuid', 'uuid')
            ->select(sprintf('%s.element', ResistanceModifier::getTableName()))
            ->jointable(CompendiumTrait::getTableName(), StatblockTrait::getTableName(), 'trait_uuid', '=', 'uuid')
            ->jointable(ResistanceModifierTrait::getTableName(), CompendiumTrait::getTableName(), 'uuid', '=', 'trait_uuid')
            ->jointable(ResistanceModifier::getTableName(), ResistanceModifierTrait::getTableName(), 'resistance_modifier_uuid', '=', 'uuid')
            ->orderBy(sprintf('%s.is_base', ResistanceModifier::getTableName()))
            ->orderBy(sprintf('%s.stage', ResistanceModifier::getTableName()), 'desc')
            ->select([
                sprintf('%s.statblock_uuid', StatblockTrait::getTableName()),
                sprintf('%s.trait_uuid as source_uuid', StatblockTrait::getTableName()),
                sprintf('%s.name as source', CompendiumTrait::getTableName()),
                sprintf('%s.element', ResistanceModifier::getTableName()),
                sprintf('%s.stage', ResistanceModifier::getTableName()),
                sprintf('%s.is_base', ResistanceModifier::getTableName()),
            ]);

        return $creatureTemplateResistances->union($traitResistances->getQuery());
    }

    /**
     * The hasMany relationship to include the roll modifiers in the query
     * @return HasMany
     */
    public function statModifiers() :HasMany {
        return $this->hasMany(StatblockTrait::class, 'statblock_uuid', 'uuid')
            ->jointable(CompendiumTrait::getTableName(), StatblockTrait::getTableName(), 'trait_uuid', '=', 'uuid')
            ->jointable(StatTrait::getTableName(), CompendiumTrait::getTableName(), 'uuid', '=', 'trait_uuid')
            ->select([
                sprintf('%s.statblock_uuid', StatblockTrait::getTableName()),
                sprintf('%s.trait_uuid as source_uuid', StatblockTrait::getTableName()),
                sprintf('%s.name as source', CompendiumTrait::getTableName()),
                sprintf('%s.stat', StatTrait::getTableName()),
                sprintf('%s.formula', StatTrait::getTableName()),
            ]);
    }

    /**
     * The hasMany relationship to include the roll modifiers in the query
     * @return HasMany
     */
    public function rollModifiers() :HasMany {
        return $this->hasMany(StatblockTrait::class, 'statblock_uuid', 'uuid')
            ->jointable(CompendiumTrait::getTableName(), StatblockTrait::getTableName(), 'trait_uuid', '=', 'uuid')
            ->jointable(RollModifierTrait::getTableName(), CompendiumTrait::getTableName(), 'uuid', '=', 'trait_uuid')
            ->select([
                sprintf('%s.statblock_uuid', StatblockTrait::getTableName()),
                sprintf('%s.trait_uuid as source_uuid', StatblockTrait::getTableName()),
                sprintf('%s.name as source', CompendiumTrait::getTableName()),
                sprintf('%s.roll_type', RollModifierTrait::getTableName()),
                sprintf('%s.formula', RollModifierTrait::getTableName()),
            ]);
    }

    /**
     * The hasMany relationship to include the roll modifiers in the query
     * @return HasMany
     */
    public function proficiencies() :HasMany {
        return $this->hasMany(StatblockTrait::class, 'statblock_uuid', 'uuid')
            ->jointable(CompendiumTrait::getTableName(), StatblockTrait::getTableName(), 'trait_uuid', '=', 'uuid')
            ->jointable(ProficiencyTrait::getTableName(), CompendiumTrait::getTableName(), 'uuid', '=', 'trait_uuid')
            ->select([
                sprintf('%s.statblock_uuid', StatblockTrait::getTableName()),
                sprintf('%s.trait_uuid as source_uuid', StatblockTrait::getTableName()),
                sprintf('%s.name as source', CompendiumTrait::getTableName()),
                sprintf('%s.skill', ProficiencyTrait::getTableName()),
                sprintf('%s.proficiency_level', ProficiencyTrait::getTableName()),
            ]);
    }

    /**
     * The hasMany relationship to include the resources in the query
     * @return HasMany
     */
    public function resources() :HasMany {
        return $this->hasMany(StatblockTrait::class, 'statblock_uuid', 'uuid')
            ->jointable(CompendiumTrait::getTableName(), StatblockTrait::getTableName(), 'trait_uuid', '=', 'uuid')
            ->jointable(ResourceTrait::getTableName(), CompendiumTrait::getTableName(), 'uuid', '=', 'trait_uuid')
            ->jointable(Resource::getTableName(), ResourceTrait::getTableName(), 'resource_uuid', '=', 'uuid')
            ->select([
                sprintf('%s.statblock_uuid', StatblockTrait::getTableName()),
                sprintf('%s.trait_uuid as source_uuid', StatblockTrait::getTableName()),
                sprintf('%s.name as source', CompendiumTrait::getTableName()),
                sprintf('%s.cap_formula', ResourceTrait::getTableName()),
                sprintf('%s.code as resource_code', Resource::getTableName()),
                sprintf('%s.name as resource_name', Resource::getTableName()),
                sprintf('%s.recharge_interval', Resource::getTableName()),
                sprintf('%s.recharge_formula', Resource::getTableName()),
            ]);
    }
}
