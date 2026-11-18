<?php

namespace App\Models\Arsenal;

use App\Models\AbstractModel;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string uuid The uuid key of the loadout
 * @property string name The display name for the loadout
 *
 * @property string warframe_uuid The uuid of the warframe
 * @property string|null primary_uuid The uuid of the primary
 * @property string|null secondary_uuid The uuid of the secondary
 * @property string|null melee_uuid The uuid of the melee
 * @property string|null companion_uuid The uuid of the companion
 * @property string|null companion_weapon_uuid The uuid of the companion weapon
 *
 * @property string created_at The creation date of the model as a string
 * @property string updated_at The date this model was last updated
 */
class Loadout extends AbstractModel
{
    use HasUuids, HasTimestamps;

    /** @var string The name of the table */
    public const string TABLE_NAME = 'arsenal__loadout';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    /** @inheritdoc */
    protected $primaryKey = 'uuid';

    /** @inheritdoc */
    protected $keyType = 'uuid';

    /** @inheritdoc */
    public $incrementing = false;

    //| Relationships

    /**
     * The relationship for the owned warframe
     * @return BelongsTo
     */
    public function Warframe(): BelongsTo {
        return $this->belongsTo(UserWarframe::class, 'warframe_uuid', 'uuid');
    }

    /**
     * The relationship for the owned primary weapon
     * @return BelongsTo
     */
    public function Primary(): BelongsTo {
        return $this->belongsTo(UserWeapon::class, 'primary_uuid', 'uuid');
    }

    /**
     * The relationship for the owned secondary weapon
     * @return BelongsTo
     */
    public function Secondary(): BelongsTo {
        return $this->belongsTo(UserWeapon::class, 'secondary_uuid', 'uuid');
    }

    /**
     * The relationship for the owned melee weapon
     * @return BelongsTo
     */
    public function Melee(): BelongsTo {
        return $this->belongsTo(UserWeapon::class, 'melee_uuid', 'uuid');
    }

    /**
     * The relationship for the owned companion
     * @return BelongsTo
     */
    public function Companion(): BelongsTo {
        return $this->belongsTo(UserCompanion::class, 'companion_uuid', 'uuid');
    }

    /**
     * The relationship for the owned companion weapon
     * @return BelongsTo
     */
    public function CompanionWeapon(): BelongsTo {
        return $this->belongsTo(UserWeapon::class, 'companion_weapon_uuid', 'uuid');
    }

    //| Getters

    /**
     * Get the warframe
     * @return UserWarframe
     */
    public function getWarframe(): UserWarframe
    {
        /** @var UserWarframe $warframe */
        $warframe = $this->Warframe()->first();
        return $warframe;
    }

    /**
     * Get the primary weapon
     * @return UserWeapon|null
     */
    public function getPrimaryWeapon(): UserWeapon|null
    {
        /** @var UserWeapon|null $weapon */
        $weapon = $this->Primary()->first();
        return $weapon;
    }

    /**
     * Get the secondary weapon
     * @return UserWeapon|null
     */
    public function getSecondaryWeapon(): UserWeapon|null
    {
        /** @var UserWeapon|null $weapon */
        $weapon = $this->Secondary()->first();
        return $weapon;
    }

    /**
     * Get the melee weapon
     * @return UserWeapon|null
     */
    public function getMeleeWeapon(): UserWeapon|null
    {
        /** @var UserWeapon|null $weapon */
        $weapon = $this->Melee()->first();
        return $weapon;
    }

    /**
     * Get the companion
     * @return UserCompanion|null
     */
    public function getCompanion(): UserCompanion|null
    {
        /** @var UserCompanion|null $companion */
        $companion = $this->Companion()->first();
        return $companion;
    }

    /**
     * Get the companion weapon
     * @return UserWeapon|null
     */
    public function getCompanionWeapon(): UserWeapon|null
    {
        /** @var UserWeapon|null $weapon */
        $weapon = $this->CompanionWeapon()->first();
        return $weapon;
    }
}
