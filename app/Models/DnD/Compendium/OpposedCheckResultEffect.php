<?php

namespace App\Models\DnD\Compendium;

use App\Models\DnD\Compendium\Interfaces\DndCompendiumModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An effect triggered by the result of an opposed check
 * @inheritdoc
 * @property string opposed_check_uuid The uuid of the opposed check that triggers the effect
 * @property string effect_uuid The uuid of the effect that is triggered
 * @property int result_type The result that triggers the effect. 0=failure, 1=success
 */
class OpposedCheckResultEffect extends DndCompendiumModel
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__compendium_opposed_check_result_effect';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    /** @inheritdoc */
    protected $casts = [
        'result_type' => 'integer',
    ];

    //| Relationships

    /**
     * The relationship for the opposed check that triggers the effect
     * @return BelongsTo
     */
    public function OpposedCheck(): BelongsTo {
        return $this->belongsTo(OpposedCheck::class, 'opposed_check_uuid', 'uuid');
    }

    /**
     * The relationship for the effect that is triggered
     * @return BelongsTo
     */
    public function Effect(): BelongsTo {
        return $this->belongsTo(Effect::class, 'effect_uuid', 'uuid');
    }
}
