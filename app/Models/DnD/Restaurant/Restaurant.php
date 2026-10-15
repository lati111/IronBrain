<?php

namespace App\Models\DnD\Restaurant;

use App\Models\Auth\User;
use Carbon\Carbon;
use App\Models\DnD\Restaurant\Interfaces\DndRestaurantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

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
 * @property float shift_total What the orders placed during the current service are worth
 * @property integer state_version How many changes have been made to the restaurant, so refreshing pages can tell when
 *                                 nothing changed
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
        'shift_total' => 'float',
        'state_version' => 'integer',
    ];

    /** @var string[] The count of changes is bookkeeping for refreshing pages rather than part of the restaurant */
    protected $hidden = ['state_version'];

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
     * Checks whether a user is one of the restaurant's players, who only control the employees assigned to them and
     * move ingredients around, rather than its owner or one of its managers
     * @param User $user The user to check
     * @return bool Whether the user is a player
     */
    public function isPlayer(User $user): bool
    {
        if ($this->owner_uuid === $user->uuid) {
            return false;
        }

        return $this->Access()->where('user_uuid', $user->uuid)->where('role', RestaurantUser::ROLE_PLAYER)->exists();
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
     * Gets the restaurant's current moment in its own time, combining its date and current time. Before a service
     * has started, this is the opening time
     * @return Carbon The current moment
     */
    public function getNow(): Carbon
    {
        return Carbon::parse($this->date->format('Y-m-d') . ' ' . ($this->current_time ?? $this->opening_time));
    }

    /**
     * Sets the restaurant's current moment, moving the date along when it passes midnight. Does not save
     * @param Carbon $now The new current moment
     */
    public function setNow(Carbon $now): void
    {
        $this->date = $now->format('Y-m-d');
        $this->current_time = $now->format('H:i:s');
    }

    /**
     * Whether a service is ongoing, which is the case while the restaurant has a current time
     * @return bool Whether a service is ongoing
     */
    public function isInService(): bool
    {
        return $this->current_time !== null;
    }

    /**
     * Gets the log of the ongoing service
     * @return ServiceHistory|null The ongoing service, or null when there is none or it started before services were logged
     */
    public function getCurrentService(): ?ServiceHistory
    {
        return $this->ServiceHistory()->whereNull('ended_at')->latest('started_at')->first();
    }

    /**
     * Gets the prices of the recipes on sale during the ongoing service
     * @return array<string, float> The sale prices, keyed by recipe uuid
     */
    public function getSalePrices(): array
    {
        $service = $this->getCurrentService();
        if ($service === null) {
            return [];
        }

        return Sale::where('service_uuid', $service->uuid)->pluck('price', 'recipe_uuid')
            ->map(fn ($price) => (float) $price)->all();
    }

    //| Money

    /** The coins below gold, from the smallest up. Each is worth ten of the previous, and gold takes everything above */
    private const array LOWER_COINS = ['b', 'cp', 'sp'];

    /**
     * Gets how much money the restaurant has, in bits
     * @return int The amount in bits
     */
    public function getTotalBits(): int
    {
        return $this->gold * 1000 + $this->silver * 100 + $this->copper * 10 + $this->bits;
    }

    /**
     * Takes an amount off the restaurant's money. Each digit comes off its own coin, so 1234 bits takes a gold, two
     * silver, three copper and four bits. A coin that runs short is made up by breaking one of the next coin up into
     * ten. Does not save
     * @param float $bits The amount in bits, rounded to whole bits
     * @throws InvalidArgumentException Thrown when the restaurant has less money than that
     */
    public function spendMoney(float $bits): void
    {
        $amount = (int) round($bits);
        if ($amount <= 0) {
            return;
        }

        if ($amount > $this->getTotalBits()) {
            throw new InvalidArgumentException(sprintf('Not enough money: %s needed, %s available',
                self::formatMoney($amount), self::formatMoney($this->getTotalBits())));
        }

        $this->bits -= $amount % 10;
        $this->copper -= intdiv($amount, 10) % 10;
        $this->silver -= intdiv($amount, 100) % 10;
        $this->gold -= intdiv($amount, 1000);

        // Short coins borrow from the next one up, which may in turn borrow from the one above it
        foreach ([['bits', 'copper'], ['copper', 'silver'], ['silver', 'gold']] as [$coin, $next]) {
            while ($this->{$coin} < 0) {
                $this->{$coin} += 10;
                $this->{$next}--;
            }
        }
    }

    /**
     * Adds an amount to the restaurant's money. Each digit goes to its own coin, so 1234 bits adds a gold, two silver,
     * three copper and four bits, leaving the coins already there as they are. Does not save
     * @param float $bits The amount in bits, rounded to whole bits
     */
    public function addMoney(float $bits): void
    {
        $amount = (int) round($bits);
        if ($amount <= 0) {
            return;
        }

        $this->bits += $amount % 10;
        $this->copper += intdiv($amount, 10) % 10;
        $this->silver += intdiv($amount, 100) % 10;
        $this->gold += intdiv($amount, 1000);
    }

    /**
     * Formats an amount of bits as coins, leaving out coins there are none of, so 1234 becomes "1gp 2sp 3cp 4b" and 50
     * becomes "5cp". Fractions of a bit stay on the bits. Matches formatMoney on the frontend
     * @param float $bits The amount in bits
     * @return string The formatted amount
     */
    public static function formatMoney(float $bits): string
    {
        $sign = $bits < 0 ? '-' : '';
        $rounded = round(abs($bits), 2);
        $whole = (int) floor($rounded);
        $fraction = $rounded - $whole;

        $parts = [];
        foreach (self::LOWER_COINS as $index => $coin) {
            $amount = $whole % 10;
            if ($index === 0) {
                $amount = round($amount + $fraction, 2);
            }

            if ($amount > 0) {
                array_unshift($parts, $amount . $coin);
            }
            $whole = intdiv($whole, 10);
        }

        if ($whole > 0) {
            array_unshift($parts, $whole . 'gp');
        }

        return count($parts) === 0 ? '0b' : $sign . implode(' ', $parts);
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
     * The relationship for the logs of the restaurant's services
     * @return HasMany
     */
    public function ServiceHistory(): HasMany {
        return $this->hasMany(ServiceHistory::class, 'restaurant_uuid', 'uuid');
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
