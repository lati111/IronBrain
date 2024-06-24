<?php

namespace App\Models\Compendium;

use App\Models\AbstractModel;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * @inheritDoc
 * @property string code The code for this creature template
 * @property string name The display name for this creature template
 * @property string created_at The creation date of the model as a string
 * @property string updated_at The date this model was last updated
 */

class CreatureTemplate extends AbstractModel
{
    use HasUuids, HasTimestamps;

    /** { @inheritdoc } */
    protected $table = 'compendium__creature_template';

    /** { @inheritdoc } */
    protected $primaryKey = 'code';

    /** { @inheritdoc } */
    protected $keyType = 'string';

    /**
     * Add a resistance modifier to this statblock
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
