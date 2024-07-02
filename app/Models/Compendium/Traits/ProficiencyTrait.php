<?php

namespace App\Models\Compendium\Traits;

use App\Models\Compendium\Interfaces\AbstractTrait;

/**
 * @inheritDoc
 * @property string skill The skill to add a proficiency in
 * @property string proficiency_level The profiency level. 0.5 for half proficient, 1 for fully proficient and 2 for expertise
 */

class ProficiencyTrait extends AbstractTrait
{
    /** { @inheritdoc } */
    protected $table = 'compendium__proficiency_trait';
}
