<?php

namespace App\Models\DnD\Restaurant;

use App\Enum\DnD\Restaurant\WorkStation;
use App\Models\DnD\Restaurant\Interfaces\RestaurantDataModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A job queued in a work area: prepping a meal, or cleaning pieces of equipment. Jobs are worked on in order, by all
 * employees in the work area together
 * @inheritdoc
 * @property WorkStation station The work area the job is queued in
 * @property string|null meal_uuid The uuid of the meal being prepped
 * @property string|null equipment_uuid The uuid of the equipment being cleaned
 * @property integer amount How many pieces of the equipment are being cleaned
 * @property float work How many minutes the job takes in total at normal speed
 * @property float progress How many minutes of the job have been done at normal speed
 * @property integer order The place of the job in the queue of its work area
 */
class Work extends RestaurantDataModel
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__restaurant_work';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    /** @inheritdoc */
    protected $casts = [
        'station'  => WorkStation::class,
        'amount'   => 'integer',
        'work'     => 'float',
        'progress' => 'float',
        'order'    => 'integer',
    ];

    /**
     * Gets how many minutes of the job are left at normal speed
     * @return float The minutes left
     */
    public function getRemaining(): float
    {
        return max(0.0, round($this->work - $this->progress, 2));
    }

    /**
     * Whether the job is done, and only waits for the rest of its area's queue before it is cleared
     * @return bool Whether it is done
     */
    public function isFinished(): bool
    {
        return $this->getRemaining() <= 0;
    }

    //| Relationships

    /**
     * The relationship for the meal being prepped
     * @return BelongsTo
     */
    public function Meal(): BelongsTo {
        return $this->belongsTo(Meal::class, 'meal_uuid', 'uuid');
    }

    /**
     * The relationship for the equipment being cleaned
     * @return BelongsTo
     */
    public function Equipment(): BelongsTo {
        return $this->belongsTo(Equipment::class, 'equipment_uuid', 'uuid');
    }
}
