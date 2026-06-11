<?php

namespace App\Models\Arsenal\Interfaces;

use App\Models\AbstractModel;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string id The id key of the item
 * @property string name The display name for the item
 * @property string|null icon The path to the icon for the item, if any
 * @property string created_at The creation date of the model as a string
 * @property string updated_at The date this model was last updated
 */

abstract class ArsenalDataModel extends AbstractModel
{
    use HasTimestamps;

    /** @var string The name of the table */
    public const string TABLE_NAME = '';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    /** @inheritdoc */
    protected $primaryKey = 'id';

    /** @inheritdoc */
    protected $keyType = 'string';

    /** @inheritdoc */
    public $incrementing = false;

    /** @inheritdoc */
    protected $casts = [
        'prime'   => 'boolean',
        'vaulted' => 'boolean',
    ];
}
