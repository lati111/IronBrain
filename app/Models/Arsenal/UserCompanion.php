<?php

namespace App\Models\Arsenal;

use App\Models\Arsenal\Interfaces\ArsenalDataModel;
use App\Models\Arsenal\Interfaces\ArsenalUserDataModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @inheritdoc
 * @property string|null name The companion's name
 * @property boolean potato Whether a potato has been applied to the companion
 * @property boolean fashioned Whether or not this companion has been fashioned
 * @property boolean built Whether or not this companion has been fully built
 * @property integer forma How much forma has been applied to this companion
 * @property string|null school Which focus school is applied to the companion
 */

class UserCompanion extends ArsenalUserDataModel
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'arsenal__user_companion';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    /**
     * Get the owned companion
     * @return Companion|null
     */
    public function getCompanion(): Companion|null
    {
        /** @var Companion $companion */
        $companion = $this->Companion()->first();
        return $companion;
    }

    /**
     * The relationship for the owned companion
     * @return BelongsTo
     */
    public function Companion(): BelongsTo {
        return $this->belongsTo(Companion::class, 'id', 'id');
    }
}
