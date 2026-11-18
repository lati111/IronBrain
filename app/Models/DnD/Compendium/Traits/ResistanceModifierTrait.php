<?php

namespace App\Models\DnD\Compendium\Traits;

use App\Models\DnD\Compendium\Interfaces\AbstractTrait;
use App\Models\DnD\Compendium\ResistanceModifier;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A trait that modifies a resistance
 * @inheritdoc
 * @property string resistance_modifier_uuid The uuid of the resistance modifier to add to the trait
 */
class ResistanceModifierTrait extends AbstractTrait
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__compendium_resistance_modifier_trait';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    /**
     * The relationship for the resistance modifier that is added
     * @return BelongsTo
     */
    public function ResistanceModifier(): BelongsTo {
        return $this->belongsTo(ResistanceModifier::class, 'resistance_modifier_uuid', 'uuid');
    }
}
