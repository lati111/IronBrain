<?php

namespace App\Models\Arsenal;

use App\Models\Arsenal\Interfaces\ArsenalDataModel;
use App\Models\Arsenal\Interfaces\ArsenalUserDataModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @inheritdoc
 * @property string|null name The user's custom name for this weapon
 * @property boolean riven Whether a riven has been obtained for this item
 * @property boolean potato Whether a potato has been applied to the weapon
 * @property boolean exilus Whether a exilus adapter has been applied to the weapon
 * @property boolean built Whether or not this weapon has been fully built
 * @property integer forma How much forma has been applied to this weapon
 * @property string|null school Which focus school is applied to the item
 */

class UserWeapon extends ArsenalUserDataModel
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'arsenal__user_weapon';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    /**
     * Get the owned weapon
     * @return Weapon|null
     */
    public function getWeapon(): Weapon|null
    {
        /** @var Weapon $weapon */
        $weapon = $this->Weapon()->first();
        return $weapon;
    }

    /**
     * The relationship for the owned weapon
     * @return BelongsTo
     */
    public function Weapon(): BelongsTo {
        return $this->belongsTo(Weapon::class, 'id', 'id');
    }
}
