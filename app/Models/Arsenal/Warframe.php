<?php

namespace App\Models\Arsenal;

use App\Models\AbstractModel;
use App\Models\Arsenal\Interfaces\ArsenalDataModel;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @inheritdoc
 * @property string description The warframe's
 * @property string|null wiki_url The link to the wiki, if it exists
 * @property bool prime Whether this is a prime item
 */

class Warframe extends ArsenalDataModel
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'arsenal__warframe';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;
}
