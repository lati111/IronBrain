<?php

namespace App\Models\Compendium\Traits;

use App\Models\AbstractModel;
use App\Models\Compendium\Action;
use App\Models\Compendium\ResistanceModifier;
use App\Models\Compendium\Resource;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * @inheritDoc
 * @property string uuid
 * @property string name The display for this trait
 * @property string description The description for this trait
 * @property string created_at The creation date of the model as a string
 * @property string updated_at The date this model was last updated
 */

class CompendiumTrait extends AbstractModel
{
    use HasUuids, HasTimestamps;

    /** { @inheritdoc } */
    protected $table = 'compendium__trait';

    /** { @inheritdoc } */
    protected $primaryKey = 'uuid';

    /**
     * Add a stat modifier to the trait
     * @param string $stat The stat to modify
     * @param string $formula The formula to modify the stat with
     * @return StatTrait The created link table
     */
    public function addStatModifier(string $stat, string $formula): StatTrait
    {
        $trait = new StatTrait();
        $trait->trait_uuid = $this->uuid;
        $trait->stat = $stat;
        $trait->formula = $formula;
        $trait->save();

        return $trait;
    }

    /**
     * Add a stat modifier to the trait
     * @param string $rollType The type of roll to affect
     * @param string $formula The formula to modify the roll with
     * @return RollModifierTrait The created link table
     */
    public function addRollModifier(string $rollType, string $formula): RollModifierTrait
    {
        $trait = new RollModifierTrait();
        $trait->trait_uuid = $this->uuid;
        $trait->roll_type = 'HEALING';
        $trait->formula = '+<2>';
        $trait->save();

        return $trait;
    }

    /**
     * Add a stat modifier to the trait
     * @param string $skill The skill to gain proficiency in
     * @param integer $level The profiency level. 0.5 for half proficient, 1 for fully proficient and 2 for expertise
     * @return ProficiencyTrait The created link table
     */
    public function addProficiency(string $skill, int $level): ProficiencyTrait
    {
        $trait = new ProficiencyTrait();
        $trait->trait_uuid = $this->uuid;
        $trait->skill = $skill;
        $trait->proficiency_level = $level;
        $trait->save();

        return $trait;
    }

    /**
     * Add an action to the trait
     * @param Action $action The action to add
     * @return ActionTrait The created link table
     */
    public function addAction(Action $action): ActionTrait
    {
        $trait = new ActionTrait();
        $trait->trait_uuid = $this->uuid;
        $trait->action_uuid = $action->uuid;
        $trait->save();

        return $trait;
    }

    /**
     * Add a resource to the trait
     * @param Resource $resource The resource to add
     * @param string $formula The formula to determine the resource cap
     * @return ResourceTrait The created link table
     */
    public function addResource(Resource $resource, string $formula): ResourceTrait
    {
        $trait = new ResourceTrait();
        $trait->trait_uuid = $this->uuid;
        $trait->resource_uuid = $resource->uuid;
        $trait->cap_formula = $formula;
        $trait->save();

        return $trait;
    }

    /**
     * Add an resistance modifier to the trait
     * @param ResistanceModifier $resistanceModifier The resistance modifier to add
     * @return ResistanceModifierTrait The created link table
     */
    public function addResistanceModifier(ResistanceModifier $resistanceModifier): ResistanceModifierTrait
    {
        $trait = new ResistanceModifierTrait();
        $trait->trait_uuid = $this->uuid;
        $trait->resistance_modifier_uuid = $resistanceModifier->uuid;
        $trait->save();

        return $trait;
    }
}
