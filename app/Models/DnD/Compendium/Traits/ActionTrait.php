<?php

namespace App\Models\DnD\Compendium\Traits;

use App\Models\DnD\Compendium\Action;
use App\Models\DnD\Compendium\Interfaces\AbstractTrait;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A trait that adds an action
 * @inheritdoc
 * @property string action_uuid The uuid of the action to add to the trait
 */
class ActionTrait extends AbstractTrait
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__compendium_action_trait';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    /**
     * The relationship for the action that is added
     * @return BelongsTo
     */
    public function Action(): BelongsTo {
        return $this->belongsTo(Action::class, 'action_uuid', 'uuid');
    }
}
