<?php

namespace App\Models;

use App\Models\Compendium\Action;
use App\Models\Compendium\StatblockTrait;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    public function unconstrainedHasOne(string $instanceString, string $foreignTable, string $foreignKey, string $localkey): HasOne {
        $instance = $this->newRelatedInstance($instanceString);

        return $this->newHasOne(
            $instance->newQuery(),
            $this,
            $foreignTable.'.'.$foreignKey,
            $localkey
        );
    }

    public function unconstrainedHasMany(string $instanceString, string $foreignTable, string $foreignKey, string $localkey): HasMany {
        $instance = $this->newRelatedInstance($instanceString);

        return $this->newHasMany(
            $instance->newQuery(),
            $this,
            $foreignTable.'.'.$foreignKey,
            $localkey
        );
    }
}
