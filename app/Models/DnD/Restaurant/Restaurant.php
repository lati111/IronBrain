<?php

namespace App\Models\DnD\Restaurant;

use App\Models\Auth\User;
use App\Models\DnD\Restaurant\Interfaces\DndRestaurantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @inheritdoc
 * @property string owner_uuid The uuid of the user that owns this restaurant
 * @property string name The display name of the restaurant
 * @property integer bits How many bits the restaurant has
 * @property integer copper How much copper the restaurant has
 * @property integer silver How much silver the restaurant has
 * @property integer gold How much gold the restaurant has
 */
class Restaurant extends DndRestaurantModel
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__restaurant_restaurant';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    /** @inheritdoc */
    protected $casts = [
        'bits'   => 'integer',
        'copper' => 'integer',
        'silver' => 'integer',
        'gold'   => 'integer',
    ];

    /**
     * Check whether a user may access this restaurant, either as owner or through granted access
     * @param User $user The user to check
     * @return bool Whether the user has access
     */
    public function hasAccess(User $user): bool
    {
        if ($this->owner_uuid === $user->uuid) {
            return true;
        }

        return $this->Access()->where('user_uuid', $user->uuid)->exists();
    }

    //| Relationships

    /**
     * The relationship for the restaurant's owner
     * @return BelongsTo
     */
    public function Owner(): BelongsTo {
        return $this->belongsTo(User::class, 'owner_uuid', 'uuid');
    }

    /**
     * The relationship for the access entries granted to other users
     * @return HasMany
     */
    public function Access(): HasMany {
        return $this->hasMany(RestaurantUser::class, 'restaurant_uuid', 'uuid');
    }

    /**
     * The relationship for the users that have been granted access
     * @return BelongsToMany
     */
    public function Users(): BelongsToMany {
        return $this->belongsToMany(User::class, RestaurantUser::TABLE_NAME, 'restaurant_uuid', 'user_uuid', 'uuid', 'uuid')
            ->using(RestaurantUser::class)
            ->withTimestamps();
    }

    /**
     * The relationship for the restaurant's recipes
     * @return HasMany
     */
    public function Recipes(): HasMany {
        return $this->hasMany(Recipe::class, 'restaurant_uuid', 'uuid');
    }

    /**
     * The relationship for the restaurant's ingredients
     * @return HasMany
     */
    public function Ingredients(): HasMany {
        return $this->hasMany(Ingredient::class, 'restaurant_uuid', 'uuid');
    }

    /**
     * The relationship for the restaurant's equipment
     * @return HasMany
     */
    public function Equipment(): HasMany {
        return $this->hasMany(Equipment::class, 'restaurant_uuid', 'uuid');
    }

    /**
     * The relationship for the owned equipment entries of the restaurant
     * @return HasMany
     */
    public function OwnedEquipment(): HasMany {
        return $this->hasMany(OwnedEquipment::class, 'restaurant_uuid', 'uuid');
    }

    /**
     * The relationship for the equipment the restaurant owns, with the amount on the pivot
     * @return BelongsToMany
     */
    public function Owns(): BelongsToMany {
        return $this->belongsToMany(Equipment::class, OwnedEquipment::TABLE_NAME, 'restaurant_uuid', 'equipment_uuid', 'uuid', 'uuid')
            ->using(OwnedEquipment::class)
            ->withPivot('uuid', 'amount')
            ->withTimestamps();
    }

    /**
     * The relationship for the restaurant's storage spaces
     * @return HasMany
     */
    public function Storages(): HasMany {
        return $this->hasMany(Storage::class, 'restaurant_uuid', 'uuid');
    }

    //| Getters

    /**
     * Get the owner of this restaurant
     * @return User
     */
    public function getOwner(): User
    {
        /** @var User $user */
        $user = $this->Owner()->first();
        return $user;
    }
}
