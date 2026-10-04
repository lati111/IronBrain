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
 * @property string role What the user may do: everything as a manager, or only control their employees as a player
 */
class RestaurantUser extends DndRestaurantModel
{
    /** @var string Has the same control over the restaurant as its owner */
    public const string ROLE_MANAGER = 'manager';
    /** @var string Only controls the employees assigned to them during service, and moves ingredients around */
    public const string ROLE_PLAYER = 'player';

    use AsPivot;

    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__restaurant_restaurant_user';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    /** Pivot attributes such as amount are filled on attach, so nothing is guarded, as with Laravel's Pivot class */
    protected $guarded = [];

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
