<?php

namespace App\Models\Arsenal;

use App\Models\AbstractModel;
use App\Models\Arsenal\Interfaces\ArsenalDataModel;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @inheritdoc
 * @property string blueprint_id The ID of the blueprint
 * @property string type The type of blueprint eg warframe, weapon etc
 * @property integer amount The amount required for the blueprint
 */

class Component extends ArsenalDataModel
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'arsenal__component';
}
