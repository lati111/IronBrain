<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @inheritDoc
 * @property string created_at The creation date of the model as a string
 * @property string updated_at The date this model was last updated
 */

abstract class AbstractModel extends Model
{
    use HasTimestamps;

    /**
     * Gets the database table name for this model
     * @return string Database table name
     */
    public static function getTableName(): string
    {
        return with(new static)->getTable();
    }
}
