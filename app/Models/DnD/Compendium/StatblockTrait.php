<?php

namespace App\Models\DnD\Compendium;

use App\Models\DnD\Compendium\Interfaces\DndCompendiumModel;
use App\Models\DnD\Compendium\Traits\CompendiumTrait;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A linking table granting a trait to a statblock
 * @inheritdoc
 * @property string statblock_uuid The uuid of the statblock the trait is granted to
 * @property string trait_uuid The uuid of the trait to apply
 */
class StatblockTrait extends DndCompendiumModel
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__compendium_statblock_trait';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    //| Relationships

    /**
     * The relationship for the statblock the trait is granted to
     * @return BelongsTo
     */
    public function Statblock(): BelongsTo {
        return $this->belongsTo(Statblock::class, 'statblock_uuid', 'uuid');
    }

    /**
     * The relationship for the trait that is granted
     * @return BelongsTo
     */
    public function Trait(): BelongsTo {
        return $this->belongsTo(CompendiumTrait::class, 'trait_uuid', 'uuid');
    }
}
