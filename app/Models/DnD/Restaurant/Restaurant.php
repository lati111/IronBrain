<?php

namespace App\Models\DnD\Restaurant;

use App\Models\Auth\User;
use Carbon\Carbon;
use App\Models\DnD\Restaurant\Interfaces\DndRestaurantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @inheritdoc
 * @property string owner_uuid The uuid of the user that owns this restaurant
 * @property string name The display name of the restaurant
 * @property Carbon date The current in-game date of the restaurant
 * @property string opening_time The time the restaurant opens, as H:i:s
 * @property string closing_time The time the restaurant closes, as H:i:s
 * @property string|null current_time The current in-game time during service as H:i:s, or null when not in service
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
        'date'   => 'date:Y-m-d',
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

    /**
     * Gets the details of the restaurant as shown and edited on its manage page. Times are given as H:i
     * @return array The details
     */
    public function getDetails(): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'date' => $this->date->format('Y-m-d'),
            'opening_time' => substr($this->opening_time, 0, 5),
            'closing_time' => substr($this->closing_time, 0, 5),
            'current_time' => $this->current_time !== null ? substr($this->current_time, 0, 5) : null,
            'gold' => $this->gold,
            'silver' => $this->silver,
            'copper' => $this->copper,
            'bits' => $this->bits,
        ];
    }

    /**
     * Starts a service, which begins at the restaurant's opening time
     */
    public function startService(): void
    {
        $this->current_time = $this->opening_time;
        $this->save();
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

    /**
     * The relationship for the restaurant's rooms
     * @return HasMany
     */
    public function Rooms(): HasMany {
        return $this->hasMany(Room::class, 'restaurant_uuid', 'uuid');
    }

    /**
     * The relationship for the restaurant's tables
     * @return HasMany
     */
    public function Tables(): HasMany {
        return $this->hasMany(Table::class, 'restaurant_uuid', 'uuid');
    }

    /**
     * The relationship for the restaurant's customers
     * @return HasMany
     */
    public function Customers(): HasMany {
        return $this->hasMany(Customer::class, 'restaurant_uuid', 'uuid');
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
