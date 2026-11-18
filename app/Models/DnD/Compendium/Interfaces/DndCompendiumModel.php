<?php

namespace App\Models\DnD\Compendium\Interfaces;

use App\Models\AbstractModel;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * @property string uuid The uuid key of the model
 * @property string created_at The creation date of the model as a string
 * @property string updated_at The date this model was last updated
 */
abstract class DndCompendiumModel extends AbstractModel
{
    use HasUuids, HasTimestamps;

    /** @var string The name of the table */
    public const string TABLE_NAME = '';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    /** @inheritdoc */
    protected $primaryKey = 'uuid';

    /** @inheritdoc */
    protected $keyType = 'string';

    /** @inheritdoc */
    public $incrementing = false;
}
