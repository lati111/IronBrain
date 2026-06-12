<?php

namespace App\Models\Arsenal\Interfaces;

use App\Models\AbstractModel;
use App\Models\Arsenal\Warframe;
use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string id The id key of the item
 * @property string name The display name for the item
 * @property string|null icon The path to the icon for the item, if any
 * @property string created_at The creation date of the model as a string
 * @property string updated_at The date this model was last updated
 */
abstract class ArsenalUserDataModel extends AbstractModel
{
    use HasUuids, HasTimestamps;

    /** @var string The name of the table */
    public const string TABLE_NAME = '';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    /** @inheritdoc */
    protected $primaryKey = 'uuid';

    /** @inheritdoc */
    protected $keyType = 'uuid';

    /** @inheritdoc */
    public $incrementing = false;

    /**
     * Get the owner of this item
     * @return User
     */
    public function getOwner(): User
    {
        /** @var User $user */
        $user = $this->Owner()->first();
        return $user;
    }

    /**
     * The relationship for the item's owner
     * @return BelongsTo
     */
    public function Owner(): BelongsTo {
        return $this->belongsTo(User::class, 'owner_uuid', 'uuid');
    }
}
