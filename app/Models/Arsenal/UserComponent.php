<?php

namespace App\Models\Arsenal;

use App\Models\Arsenal\Interfaces\ArsenalDataModel;
use App\Models\Arsenal\Interfaces\ArsenalUserDataModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @inheritdoc
 * @property integer amount How much of this component you own
 */

class UserComponent extends ArsenalUserDataModel
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'arsenal__user_component';

    /**
     * Get the owned component
     * @return Component|null
     */
    public function getComponent(): Component|null
    {
        /** @var Component $component */
        $component = $this->Component()->first();
        return $component;
    }

    /**
     * The relationship for the owned component
     * @return BelongsTo
     */
    public function Component(): BelongsTo {
        return $this->belongsTo(Component::class, 'id', 'id');
    }
}
