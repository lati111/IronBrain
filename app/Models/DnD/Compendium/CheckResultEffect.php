<?php

namespace App\Models\DnD\Compendium;

use App\Models\DnD\Compendium\Interfaces\DndCompendiumModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An effect triggered by the result of a check
 * @inheritdoc
 * @property string check_uuid The uuid of the check that triggers the effect
 * @property string effect_uuid The uuid of the effect that is triggered
 * @property int result_type The result that triggers the effect. 0=failure, 1=success
 */
class CheckResultEffect extends DndCompendiumModel
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__compendium_check_result_effect';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    /** @inheritdoc */
    protected $casts = [
        'result_type' => 'integer',
    ];

    //| Relationships

    /**
     * The relationship for the check that triggers the effect
     * @return BelongsTo
     */
    public function Check(): BelongsTo {
        return $this->belongsTo(Check::class, 'check_uuid', 'uuid');
    }

    /**
     * The relationship for the effect that is triggered
     * @return BelongsTo
     */
    public function Effect(): BelongsTo {
        return $this->belongsTo(Effect::class, 'effect_uuid', 'uuid');
    }
}
