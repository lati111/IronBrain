<?php

namespace App\Models\Compendium\Docs;

use App\Models\AbstractModel;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * @property string uuid
 * @property string article_uuid The uuid of the article attached to this location
 * @property string title The title given to this segment
 * @property string content The content for this segment
 * @property int order The order in which this item appears in the article
 * @property boolean dm_only Whether this segment should only be shown to DMs
 * @property boolean private Whether this segment should only be shown to the DM and the player
 * @property string created_at The creation date of the model as a string
 * @property string updated_at The date this model was last updated
 */

class ArticleSegment extends AbstractModel
{
    use HasUuids, HasTimestamps;

    /** { @inheritdoc } */
    protected $table = 'compendium__article_segment';

    /** { @inheritdoc } */
    protected $primaryKey = 'uuid';
}
