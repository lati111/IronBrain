<?php

namespace App\Models\DnD\Compendium\Traits;

use App\Models\DnD\Compendium\Interfaces\AbstractTrait;
use App\Models\DnD\Compendium\Resource;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A trait that adds a resource
 * @inheritdoc
 * @property string resource_uuid The uuid of the resource to add to the trait
 * @property string cap_formula The formula used to calculate the resource cap
 */
class ResourceTrait extends AbstractTrait
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__compendium_resource_trait';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    /**
     * The relationship for the resource that is added
     * @return BelongsTo
     */
    public function Resource(): BelongsTo {
        return $this->belongsTo(Resource::class, 'resource_uuid', 'uuid');
    }
}
