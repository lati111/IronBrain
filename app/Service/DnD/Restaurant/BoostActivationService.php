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
     * Activates a boost during service on one of its targets: a single cooker, a piece of equipment, an area of the
     * kitchen, or a planned, prepped, cooking or cooked meal. Minutes it takes off are done straight away on the prep or
     * cooking of what it is on, finishing it when nothing is left. A meal gets its quality and freshness bonuses. A
     * speed bonus lasts the boost's duration
     * @param Restaurant $restaurant The restaurant
     * @param Boost $boost The boost
     * @param Equipment|null $equipment The equipment it is activated on, if any
     * @param Meal|null $meal The meal it is activated on, if any
     * @param string|null $area The area it is activated on, one of the Boost::AREA constants, if any
     * @param int|null $cookerSlot The single cooker of the equipment it is activated on, from 1, or null for all of it
     * @throws InvalidArgumentException Thrown when no service is ongoing, nothing is chosen to activate it on, it isn't
     *                                  for what it is activated on, or it is still active there
     */
    public function activateBoost(Restaurant $restaurant, Boost $boost, ?Equipment $equipment, ?Meal $meal, ?string $area, ?int $cookerSlot = null): void
    {
        if (!$restaurant->isInService()) {
            throw new InvalidArgumentException('Boosts are only activated during service');
        }

        $now = $restaurant->getNow();
        $target = $this->getTarget($equipment, $meal, $area, $cookerSlot, $now);
        if ($target === null) {
            throw new InvalidArgumentException('Choose what to boost');
        }
        if (!$boost->allows($target)) {
            throw new InvalidArgumentException(sprintf("%s can't be used on %s", $boost->name, self::TARGET_LABELS[$target]));
        }

        // Only what it was dropped on counts, so a meal's equipment or area doesn't get boosted along with it
        if ($meal !== null) {
            [$equipment, $area, $cookerSlot] = [null, null, null];
        } elseif ($equipment !== null) {
            $area = null;
        }

        // The same boost doesn't stack on the same thing, though different boosts do
        $stacked = ActiveBoost::where('restaurant_uuid', $restaurant->uuid)->where('boost_uuid', $boost->uuid)
            ->where('equipment_uuid', $equipment?->uuid)->where('cooker_slot', $cookerSlot)
            ->where('meal_uuid', $meal?->uuid)->where('area', $area)
            ->where('expires_at', '>', $now)->with(['Equipment', 'Meal.Recipe'])->first();
        if ($stacked !== null) {
            throw new InvalidArgumentException(sprintf('%s is already active on %s', $boost->name, $this->boostService->describeTarget($stacked)));
        }

        if ($cookerSlot !== null && ($equipment === null || $equipment->category !== EquipmentCategory::COOKER
                || $cookerSlot < 1 || $cookerSlot > $this->inventoryService->getTotalStock($equipment))) {
            throw new InvalidArgumentException('There is no such cooker to boost');
        }

        DB::transaction(function () use ($restaurant, $boost, $equipment, $meal, $area, $cookerSlot, $now) {
            if ($meal !== null) {
                $this->improveMeal($meal, $boost, $now);
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
     * Cancels a boost that is active, so it stops right away. What was done at its speed so far is kept, while what
     * it did straight away when activated, such as minutes off or a quality bonus, stays done
     * @param Restaurant $restaurant The restaurant
     * @param ActiveBoost $active The active boost
     * @throws InvalidArgumentException Thrown when it has already run out
     */
    public function cancelBoost(Restaurant $restaurant, ActiveBoost $active): void
    {
        $now = $restaurant->getNow();
        if ($active->expires_at->lte($now)) {
            throw new InvalidArgumentException($active->name . ' has already run out');
        }

        DB::transaction(function () use ($restaurant, $active, $now) {
            $active->expires_at = $now->copy()->max($active->starts_at);
            $active->save();

            // Meals in a cooker go back to their own speed from now on, so they are done later
            $this->boostService->rescheduleAll($restaurant, $now);
        });
    }

    /** @var array<string, string> How each target is named in messages */
    private const array TARGET_LABELS = [
        Boost::TARGET_COOKER => 'a cooker',
        Boost::TARGET_EQUIPMENT => 'equipment',
        Boost::TARGET_COOKING_AREA => 'the cooking area',
        Boost::TARGET_PREP_AREA => 'the prep area',
        Boost::TARGET_CLEANING_AREA => 'the cleaning area',
        Boost::TARGET_PLANNED_MEAL => 'a planned meal',
        Boost::TARGET_PREPPED_MEAL => 'a prepped meal',
        Boost::TARGET_COOKING_MEAL => 'a cooking meal',
        Boost::TARGET_COOKED_MEAL => 'a cooked meal',
    ];

    /**
     * Works out which kind of target a boost is activated on. A meal is planned, prepped, cooking while in a cooker,
     * or cooked once it is done. Equipment is a cooker when a single one of it is chosen or it is cooker equipment
     * @param Carbon $now The restaurant's current time
     * @return string|null The target, one of the Boost::TARGET constants, or null when nothing is chosen
     */
    private function getTarget(?Equipment $equipment, ?Meal $meal, ?string $area, ?int $cookerSlot, Carbon $now): ?string
    {
        if ($meal !== null) {
            return match ($meal->getStatusAt($now)) {
                Meal::STATUS_PLANNED => Boost::TARGET_PLANNED_MEAL,
                Meal::STATUS_PREPPED => Boost::TARGET_PREPPED_MEAL,
                Meal::STATUS_COOKING => Boost::TARGET_COOKING_MEAL,
                default => Boost::TARGET_COOKED_MEAL,
            };
        }

        if ($equipment !== null) {
            return $cookerSlot !== null || $equipment->category === EquipmentCategory::COOKER ? Boost::TARGET_COOKER : Boost::TARGET_EQUIPMENT;
        }

        return Boost::AREAS[$area ?? ''] ?? null;
    }

    /**
     * Gives a meal a boost's quality and freshness bonuses. A meal already being served starts spoiling that much
     * later or sooner straight away, while any other meal stays fresh that much longer once it is served
     * @param Meal $meal The meal
     * @param Boost $boost The boost
     * @param Carbon $now The restaurant's current time
     */
    private function improveMeal(Meal $meal, Boost $boost, Carbon $now): void
    {
        if ($boost->quality_bonus === 0 && $boost->fresh_for_bonus === 0) {
            return;
        }

        $meal->quality_bonus += $boost->quality_bonus;
        $meal->fresh_for_bonus += $boost->fresh_for_bonus;
        if ($meal->getStatusAt($now) === Meal::STATUS_SERVING && $meal->expires_at !== null) {
            $meal->expires_at = $meal->expires_at->copy()->addMinutes($boost->fresh_for_bonus);
        }
        $meal->save();
    }

    /**
     * Does minutes of work straight away on what a boost is activated on: a prepped meal gets a head start on cooking,
     * a cooking meal cooks on, a planned meal and meals being prepped get prep done, and pieces waiting to be cleaned
     * get cleaning done.
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

        // A planned meal gets prep done, once it is queued for it
        if ($meal !== null && $meal->status === Meal::STATUS_PLANNED) {
            $jobs = $this->workQueueService->getQueue($restaurant, WorkStation::PREP)->filter(fn (Work $job) => $job->meal_uuid === $meal->uuid);
            $this->workQueueService->cutJobs($restaurant, WorkStation::PREP, $jobs, $minutes, $now);
            return;
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
