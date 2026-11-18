<?php

namespace App\Models\DnD\Compendium;

use App\Models\DnD\Compendium\Interfaces\DndCompendiumModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Forces a check from an effect
 * @inheritdoc
 * @property string effect_uuid The uuid of the effect that forces the check
 * @property string check_uuid The uuid of the check that is forced
 */
class CheckEffect extends DndCompendiumModel
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__compendium_check_effect';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    //| Relationships

    /**
     * The relationship for the effect that forces the check
     * @return BelongsTo
     */
    public function Effect(): BelongsTo {
        return $this->belongsTo(Effect::class, 'effect_uuid', 'uuid');
    }

    /**
     * The relationship for the check that is forced
     * @return BelongsTo
     */
    public function Check(): BelongsTo {
        return $this->belongsTo(Check::class, 'check_uuid', 'uuid');
    }
}
