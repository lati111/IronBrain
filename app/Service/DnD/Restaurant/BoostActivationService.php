<?php

namespace App\Service\DnD\Restaurant;

use App\Enum\DnD\Restaurant\EquipmentCategory;
use App\Enum\DnD\Restaurant\EquipmentStage;
use App\Enum\DnD\Restaurant\WorkStation;
use App\Models\DnD\Restaurant\ActiveBoost;
use App\Models\DnD\Restaurant\Boost;
use App\Models\DnD\Restaurant\Equipment;
use App\Models\DnD\Restaurant\Meal;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\Work;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Activating boosts during service. A boost can take minutes off the work on what it is on straight away, add to a
 * meal's quality, and speed up what it is on for its duration, which BoostService then takes into account
 */
class BoostActivationService
{
    public function __construct(
        private readonly WorkQueueService $workQueueService,
        private readonly BoostService $boostService,
        private readonly InventoryService $inventoryService,
    ) {
    }

    /**
     * Activates a boost during service on what it is for: a piece of equipment, a prepped or cooking meal, or an area
     * of the kitchen. Minutes it takes off are done straight away on the prep or cooking of what it is on, finishing
     * it when nothing is left. A meal gets its quality bonus. A speed bonus lasts the boost's duration
     * @param Restaurant $restaurant The restaurant
     * @param Boost $boost The boost
     * @param Equipment|null $equipment The equipment it is activated on, for equipment boosts
     * @param Meal|null $meal The meal it is activated on, for meal boosts
     * @param string|null $area The area it is activated on, one of the Boost::AREA constants, for area boosts
     * @param int|null $cookerSlot The single cooker of the equipment it is activated on, from 1, or null for all of it
     * @throws InvalidArgumentException Thrown when no service is ongoing, or it is activated on something it isn't for
     */
    public function activateBoost(Restaurant $restaurant, Boost $boost, ?Equipment $equipment, ?Meal $meal, ?string $area, ?int $cookerSlot = null): void
    {
        if (!$restaurant->isInService()) {
            throw new InvalidArgumentException('Boosts are only activated during service');
        }

        $now = $restaurant->getNow();
        match ($boost->target) {
            Boost::TARGET_EQUIPMENT => $equipment !== null ?: throw new InvalidArgumentException('Choose the equipment to boost'),
            Boost::TARGET_AREA => in_array($area, Boost::AREAS, true) ?: throw new InvalidArgumentException('Choose the area to boost'),
            default => $meal !== null ?: throw new InvalidArgumentException('Choose the meal to boost'),
        };

        if ($meal !== null && !in_array($meal->getStatusAt($now), [Meal::STATUS_PREPPED, Meal::STATUS_COOKING], true)) {
            throw new InvalidArgumentException('Only prepped or cooking meals can be boosted');
        }

        if ($cookerSlot !== null && ($equipment === null || $equipment->category !== EquipmentCategory::COOKER
                || $cookerSlot < 1 || $cookerSlot > $this->inventoryService->getTotalStock($equipment))) {
            throw new InvalidArgumentException('There is no such cooker to boost');
        }

        DB::transaction(function () use ($restaurant, $boost, $equipment, $meal, $area, $cookerSlot, $now) {
            if ($meal !== null && $boost->quality_bonus !== 0) {
                $meal->quality_bonus += $boost->quality_bonus;
                $meal->save();
            }

            if ($boost->minutes_off > 0) {
                $this->cutTime($restaurant, $boost->minutes_off, $now, $equipment, $meal, $area, $cookerSlot);
            }

            if ($boost->lasts()) {
                $active = new ActiveBoost();
                $active->restaurant_uuid = $restaurant->uuid;
                $active->boost_uuid = $boost->uuid;
                $active->name = $boost->name;
                $active->speed_bonus = $boost->speed_bonus;
                $active->equipment_uuid = $equipment?->uuid;
                $active->cooker_slot = $cookerSlot;
                $active->meal_uuid = $meal?->uuid;
                $active->area = $area;
                $active->starts_at = $now;
                $active->expires_at = $now->copy()->addMinutes($boost->duration);
                $active->save();

                // Meals in a cooker go faster from now on, so they are done sooner
                $this->boostService->rescheduleAll($restaurant, $now);
            }
        });
    }

    /**
     * Does minutes of work straight away on what a boost is activated on: a prepped meal gets a head start on cooking,
     * a cooking meal cooks on, meals being prepped get prep done, and pieces waiting to be cleaned get cleaning done.
     * Equipment counts for the meals using it in the stage they are in, a single cooker for the meal in it, and an area
     * for everything in it
     * @param Restaurant $restaurant The restaurant
     * @param float $minutes How many minutes of work are done
     * @param Carbon $now The restaurant's current time
     * @param Equipment|null $equipment The equipment the boost is on, if any
     * @param Meal|null $meal The meal the boost is on, if any
     * @param string|null $area The area the boost is on, if any
     * @param int|null $cookerSlot The single cooker of the equipment the boost is on, if any
     */
    private function cutTime(Restaurant $restaurant, float $minutes, Carbon $now, ?Equipment $equipment, ?Meal $meal, ?string $area, ?int $cookerSlot): void
    {
        if ($area === Boost::AREA_CLEANING) {
            $jobs = $this->workQueueService->getQueue($restaurant, WorkStation::CLEANING);
            $this->workQueueService->cutJobs($restaurant, WorkStation::CLEANING, $jobs, $minutes, $now);
            return;
        }

        $cooking = $this->boostService->getCookingMeals($restaurant)->filter(fn (Meal $candidate) => match (true) {
            $meal !== null => $candidate->uuid === $meal->uuid,
            $cookerSlot !== null => $candidate->cooker_uuid === $equipment->uuid && $candidate->cooker_slot === $cookerSlot,
            $equipment !== null => in_array($equipment->uuid, $this->boostService->getStageEquipment($candidate, EquipmentStage::COOKING), true),
            default => $area === Boost::AREA_COOKING,
        });
        foreach ($cooking as $candidate) {
            $candidate->cooking_progress = min((float) $candidate->Recipe->cooking_time, $candidate->cooking_progress + $minutes);
            $this->boostService->reschedule($candidate, $now);
        }

        if ($meal !== null && $meal->status === Meal::STATUS_PREPPED) {
            $meal->cooking_progress = min((float) $meal->getRecipe()->cooking_time, $meal->cooking_progress + $minutes);
            $meal->save();
        }

        if ($meal !== null || $area === Boost::AREA_COOKING || $cookerSlot !== null) {
            return;
        }

        // Prep gets done on everything in the prep area, or on the meals using the boosted equipment for prep
        $jobs = $this->workQueueService->getQueue($restaurant, WorkStation::PREP)->filter(fn (Work $job) => $equipment === null
            || ($job->Meal !== null && in_array($equipment->uuid, $this->boostService->getStageEquipment($job->Meal, EquipmentStage::PREP), true)));
        $this->workQueueService->cutJobs($restaurant, WorkStation::PREP, $jobs, $minutes, $now);
    }
}
