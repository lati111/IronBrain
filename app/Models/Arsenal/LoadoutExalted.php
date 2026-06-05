<?php

namespace App\Models\Arsenal;

use App\Models\Arsenal\Interfaces\ArsenalDataModel;
use App\Models\Arsenal\Interfaces\ArsenalUserDataModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string uuid The uuid key
 *
 * @property string loadout_uuid The uuid of the loadout
 * @property string weapon_uuid The uuid of the exalted weapon
 *
 * @property string created_at The creation date of the model as a string
 * @property string updated_at The date this model was last updated
 */
class LoadoutExalted extends ArsenalUserDataModel
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'arsenal__loadout_exalted';
}
