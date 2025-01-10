<?php

namespace App\Models\Compendium\Docs;

use App\Models\AbstractModel;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * {@inheritdoc}
 * @property string|null titles A list of titles given to this character, seperated by ,s
 * @property string|null image_src The filename for the character image, if any
 */

class Character extends AbstractArticle
{
    /** { @inheritdoc } */
    protected $table = 'compendium__character';
}
