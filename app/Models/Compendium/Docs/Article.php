<?php

namespace App\Models\Compendium\Docs;

use App\Models\AbstractModel;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * @property string uuid
 * @property string player_uuid The uuid of the player that created this article
 * @property string campaign_uuid The uuid of the campaign this article belongs to
 * @property string name The name given to this article
 * @property string|null description A short description of the article topic
 * @property string|null tags A list of tags given to this character, seperated by ,s
 * @property boolean dm_access Whether this article should be shown to DMs
 * @property boolean private Whether this article should only be shown to the DM and the player
 * @property string created_at The creation date of the model as a string
 * @property string updated_at The date this model was last updated
 */

class Article extends AbstractModel
{
    use HasUuids, HasTimestamps;

    /** { @inheritdoc } */
    protected $table = 'compendium__article';

    /** { @inheritdoc } */
    protected $primaryKey = 'uuid';

    /** @var array|string[] An array containing the keys for all existing article types. */
    public const array TYPE_KEYS = [
        'character',
    ];

    /** @var array|class-string[] An associative array matching the article type class to it's key. */
    public const array TYPE = [
        'character' => Character::class,
    ];
}
