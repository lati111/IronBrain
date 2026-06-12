<?php

namespace App\Models\Arsenal;

use App\Models\Arsenal\Interfaces\ArsenalDataModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @inheritdoc
 * @property string description The weapon's description
 * @property string type The type of weapon eg primary, secondary etc
 * @property string weapon_type The class of weapon eg rifle, shotgun
 * @property string exalted_id The id of the warframe this exalted weapon belongs to
 * @property string|null wiki_url The link to the wiki, if it exists
 * @property bool prime Whether this is a prime item
 */

class Weapon extends ArsenalDataModel
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'arsenal__weapon';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    /**
     * Get the warframe this exalted weapon belongs to
     * @return Warframe|null
     */
    public function getWarframe(): Warframe|null
    {
        /** @var Warframe $warframe */
        $warframe = $this->Warframe()->first();
        return $warframe;
    }

    /**
     * The relationship for the exalted weapon's owner
     * @return BelongsTo
     */
    public function Warframe(): BelongsTo {
        return $this->belongsTo(Warframe::class, 'exalted_id', 'id');
    }
}
