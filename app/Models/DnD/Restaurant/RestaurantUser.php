<?php

namespace App\Models\DnD\Restaurant;

use App\Models\Auth\User;
use App\Models\DnD\Restaurant\Interfaces\DndRestaurantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Concerns\AsPivot;

/**
 * @inheritdoc
 * @property string restaurant_uuid The uuid of the restaurant access is granted to
 * @property string user_uuid The uuid of the user that is granted access
 */
class RestaurantUser extends DndRestaurantModel
{
    use AsPivot;

    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__restaurant_restaurant_user';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    //| Relationships

    /**
     * The relationship for the restaurant access is granted to
     * @return BelongsTo
     */
    public function Restaurant(): BelongsTo {
        return $this->belongsTo(Restaurant::class, 'restaurant_uuid', 'uuid');
    }

    /**
     * The relationship for the user that is granted access
     * @return BelongsTo
     */
    public function User(): BelongsTo {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }

    //| Getters

    /**
     * Get the restaurant access is granted to
     * @return Restaurant
     */
    public function getRestaurant(): Restaurant
    {
        /** @var Restaurant $restaurant */
        $restaurant = $this->Restaurant()->first();
        return $restaurant;
    }

    /**
     * Get the user that is granted access
     * @return User
     */
    public function getUser(): User
    {
        /** @var User $user */
        $user = $this->User()->first();
        return $user;
    }
}
