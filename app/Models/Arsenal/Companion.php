<?php

namespace App\Models\Arsenal;

use App\Models\AbstractModel;
use App\Models\Arsenal\Interfaces\ArsenalDataModel;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @inheritdoc
 * @property string type The companion type, eg. pet, sentinel
 * @property string pet_type The companion type, eg. kubrow, kavat
 * @property string description The warframe's description
 */

class Companion extends ArsenalDataModel
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'arsenal__companion';
}
