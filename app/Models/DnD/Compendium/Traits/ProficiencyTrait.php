<?php

namespace App\Models\DnD\Compendium\Traits;

use App\Models\DnD\Compendium\Interfaces\AbstractTrait;

/**
 * A trait that gives a proficiency in a skill or stat
 * @inheritdoc
 * @property string skill The skill or stat to add a proficiency in
 * @property float proficiency_level The proficiency level. 0.5 for half proficient, 1 for fully proficient and 2 for expertise
 */
class ProficiencyTrait extends AbstractTrait
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__compendium_proficiency_trait';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    /** @inheritdoc */
    protected $casts = [
        'proficiency_level' => 'float',
    ];
}
