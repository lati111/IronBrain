<?php

namespace App\Models\DnD\Compendium\Interfaces;

use App\Models\DnD\Compendium\Traits\CompendiumTrait;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The base for the tables that add a specific kind of effect to a trait
 * @inheritdoc
 * @property string trait_uuid The uuid of the trait this applies to
 */
abstract class AbstractTrait extends DndCompendiumModel
{
    /**
     * The relationship for the trait this applies to
     * @return BelongsTo
     */
    public function Trait(): BelongsTo {
        return $this->belongsTo(CompendiumTrait::class, 'trait_uuid', 'uuid');
    }
}
