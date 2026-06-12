<?php

namespace App\Models\Arsenal;

use App\Models\Arsenal\Interfaces\ArsenalDataModel;
use App\Models\Arsenal\Interfaces\ArsenalUserDataModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @inheritdoc
 * @property string|null name The user's custom name for this warframe
 * @property boolean potato Whether a potato has been applied to the warframe
 * @property boolean exilus Whether a exilus adapter has been applied to the warframe
 * @property boolean fashioned Whether or not this frame has been fashioned
 * @property boolean built Whether or not this frame has been fully built
 * @property integer forma How much forma has been applied to this warframe
 * @property integer shards How many shards have been applied to this warframe (0–5)
 * @property string|null school Which focus school is applied to the item
 */

class UserWarframe extends ArsenalUserDataModel
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'arsenal__user_warframe';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    /**
     * Get the owned warframe
     * @return Warframe|null
     */
    public function getWarframe(): Warframe|null
    {
        /** @var Warframe $warframe */
        $warframe = $this->Warframe()->first();
        return $warframe;
    }

    /**
     * The relationship for the owned warframe
     * @return BelongsTo
     */
    public function Warframe(): BelongsTo {
        return $this->belongsTo(Warframe::class, 'id', 'id');
    }
}
