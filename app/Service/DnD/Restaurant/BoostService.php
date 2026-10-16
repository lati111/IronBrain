<?php

namespace App\Service\DnD\Restaurant;

use App\Enum\DnD\Restaurant\EquipmentStage;
use App\Models\DnD\Restaurant\ActiveBoost;
use App\Models\DnD\Restaurant\Boost;
use App\Models\DnD\Restaurant\Equipment;
use App\Models\DnD\Restaurant\Meal;
use App\Models\DnD\Restaurant\Restaurant;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * How fast meals are prepped and cooked, and cooking as the clock moves. A meal works at normal speed, plus what the
 * equipment it uses in a stage adds: each piece adds its multiplier less one, so a multiplier of 1.5 adds half. The
 * cooker counts for cooking. Active boosts add their bonus on top while they last, whether they are on the meal, on
 * equipment it uses, or on the area it is in. As boosts run out partway through, time is worked through in stretches
 * between the moments they expire, keeping what was done at each speed
 */
class BoostService
{
    /** @var float The slowest anything is worked on, so nothing stops altogether */
    public const float MIN_SPEED = 0.1;

    /** @var array<string, float> The multiplier of each piece of equipment looked up so far, keyed by uuid */
    private array $multipliers = [];

    //| Active boosts

    /**
     * Gets the boosts of a restaurant that haven't run out by a moment, as these are the ones that can still count
     * @param Restaurant $restaurant The restaurant
     * @param Carbon $from The moment, in the restaurant's time
     * @return Collection<ActiveBoost> The boosts
     */
    public function getBoosts(Restaurant $restaurant, Carbon $from): Collection
    {
        return ActiveBoost::where('restaurant_uuid', $restaurant->uuid)->where('expires_at', '>', $from)->orderBy('expires_at')->get();
    }

    /**
     * Gets the moments boosts run out between two moments, which is where speeds change
     * @param Collection<ActiveBoost> $boosts The boosts
     * @return Carbon[] The moments, in order
     */
    public function getBoundaries(Collection $boosts, Carbon $from, ?Carbon $to): array
    {
        return $boosts->pluck('expires_at')
            ->filter(fn (Carbon $moment) => $moment->gt($from) && ($to === null || $moment->lt($to)))
            ->unique(fn (Carbon $moment) => $moment->getTimestamp())
            ->sort()->values()->all();
    }

    /**
     * Describes the boosts active at a moment, with what each is on and how long it has left
     * @param Restaurant $restaurant The restaurant
     * @param Carbon $now The restaurant's current time
     * @return array The boosts
     */
    public function describeActive(Restaurant $restaurant, Carbon $now): array
    {
        return ActiveBoost::where('restaurant_uuid', $restaurant->uuid)->where('expires_at', '>', $now)->where('starts_at', '<=', $now)
            ->with(['Equipment', 'Meal.Recipe'])->orderBy('expires_at')->get()
            ->map(fn (ActiveBoost $boost) => [
                'uuid' => $boost->uuid,
                'name' => $boost->name,
                'speed_bonus' => $boost->speed_bonus,
                'equipment_uuid' => $boost->equipment_uuid,
                'cooker_slot' => $boost->cooker_slot,
                'meal_uuid' => $boost->meal_uuid,
                'area' => $boost->area,
                'target_name' => $this->describeTarget($boost),
                'minutes_left' => max(0, (int) ceil(($boost->expires_at->getTimestamp() - $now->getTimestamp()) / 60)),
            ])->values()->all();
    }

    /**
     * Names what an active boost is on, such as "Stove 2", "Stew" or "Cooking area"
     */
    private function describeTarget(ActiveBoost $boost): string
    {
        if ($boost->Equipment !== null) {
            return $boost->cooker_slot !== null ? $boost->Equipment->name . ' ' . $boost->cooker_slot : $boost->Equipment->name;
        }

        return $boost->Meal?->Recipe?->name ?? ($boost->area !== null ? ucfirst($boost->area) . ' area' : 'Unknown');
    }

    //| Speeds

    /**
     * Gets how fast a meal is worked on in a stage at a moment
     * @param Meal $meal The meal
     * @param EquipmentStage $stage The stage: prep or cooking
     * @param Carbon $at The moment, in the restaurant's time
     * @param Collection<ActiveBoost> $boosts The boosts that may be active
     * @return float The speed, where 1 is normal
     */
    public function getMealSpeed(Meal $meal, EquipmentStage $stage, Carbon $at, Collection $boosts): float
    {
        $equipment = $this->getStageEquipment($meal, $stage);
        $speed = 1.0;
        foreach ($equipment as $uuid) {
            $speed += $this->getMultiplier($uuid) - 1;
        }

        $area = $stage === EquipmentStage::COOKING ? Boost::AREA_COOKING : Boost::AREA_PREP;
        foreach ($boosts as $boost) {
            if ($boost->isActiveAt($at) && ($boost->meal_uuid === $meal->uuid || $boost->area === $area || $this->isOnEquipmentOf($boost, $meal, $equipment))) {
                $speed += $boost->speed_bonus;
            }
        }

        return max(self::MIN_SPEED, round($speed, 4));
    }

    /**
     * Checks whether a boost is on equipment a meal works with: on the equipment as a whole, or on the single cooker
     * the meal is in
     * @param string[] $equipment The uuids of the equipment the meal works with in its stage
     */
    public function isOnEquipmentOf(ActiveBoost $boost, Meal $meal, array $equipment): bool
    {
        if ($boost->equipment_uuid === null || !in_array($boost->equipment_uuid, $equipment, true)) {
            return false;
        }

        return $boost->cooker_slot === null || ($boost->equipment_uuid === $meal->cooker_uuid && $boost->cooker_slot === $meal->cooker_slot);
    }

    /**
     * Gets how fast work goes in a whole area at a moment: at normal speed, plus the boosts on that area. This is how
     * fast conversions are prepped, and how much faster cleaners clean
     * @param string $area The area, one of the Boost::AREA constants
     * @param Carbon $at The moment, in the restaurant's time
     * @param Collection<ActiveBoost> $boosts The boosts that may be active
     * @return float The speed, where 1 is normal
     */
    public function getAreaSpeed(string $area, Carbon $at, Collection $boosts): float
    {
        $speed = 1.0 + $boosts->filter(fn (ActiveBoost $boost) => $boost->area === $area && $boost->isActiveAt($at))->sum('speed_bonus');
        return max(self::MIN_SPEED, round($speed, 4));
    }

    /**
     * Gets the equipment a meal works with in a stage, each piece once, with the cooker it is in when cooking
     * @return string[] The uuids of the equipment
     */
    public function getStageEquipment(Meal $meal, EquipmentStage $stage): array
    {
        $uuids = array_column(array_filter($meal->equipment ?? [], fn (array $entry) => $entry['stage'] === $stage->value), 'equipment_uuid');
        if ($stage === EquipmentStage::COOKING && $meal->cooker_uuid !== null) {
            $uuids[] = $meal->cooker_uuid;
        }

        return array_values(array_unique($uuids));
    }

    private function getMultiplier(string $uuid): float
    {
        return $this->multipliers[$uuid] ??= (float) (Equipment::where('uuid', $uuid)->value('multiplier') ?? 1.0);
    }

    //| Cooking

    /**
     * Cooks the meals that are still cooking over a stretch of time, finishing those that get done along the way
     * @param Restaurant $restaurant The restaurant
     * @param Carbon $from When the stretch starts, in the restaurant's time
     * @param Carbon $to When it ends
     */
    public function advanceCooking(Restaurant $restaurant, Carbon $from, Carbon $to): void
    {
        $boosts = $this->getBoosts($restaurant, $from);
        foreach ($this->getCookingMeals($restaurant) as $meal) {
            $finishedAt = $this->cook($meal, $from, $to, $boosts);
            $this->schedule($meal, $finishedAt ?? $this->project($meal, $to, $boosts));
            $meal->save();
        }
    }

    /**
     * Works out again when a cooking meal is done, after its progress or speed changed. A meal with nothing left to
     * cook is done there and then
     * @param Meal $meal The meal, cooking
     * @param Carbon $now The restaurant's current time
     * @param Collection<ActiveBoost>|null $boosts The boosts that may be active, or null to look them up
     */
    public function reschedule(Meal $meal, Carbon $now, ?Collection $boosts = null): void
    {
        $boosts ??= $this->getBoosts($meal->getRestaurant(), $now);
        $this->schedule($meal, $this->project($meal, $now, $boosts));
        $meal->save();
    }

    /**
     * Reschedules every meal still cooking, as when a boost changed how fast they go
     */
    public function rescheduleAll(Restaurant $restaurant, Carbon $now): void
    {
        $boosts = $this->getBoosts($restaurant, $now);
        foreach ($this->getCookingMeals($restaurant) as $meal) {
            $this->reschedule($meal, $now, $boosts);
        }
    }

    /**
     * Gets the meals of a restaurant in a cooker that aren't done yet. A meal whose done time has passed is done,
     * whatever progress it has, as that is what its status goes by
     * @return Collection<Meal> The meals
     */
    public function getCookingMeals(Restaurant $restaurant): Collection
    {
        $now = $restaurant->getNow();

        return Meal::where('restaurant_uuid', $restaurant->uuid)->where('status', Meal::STATUS_COOKING)->with('Recipe')->get()
            ->filter(fn (Meal $meal) => $meal->cooking_progress < $meal->Recipe->cooking_time
                && ($meal->cooking_ends_at === null || $meal->cooking_ends_at->gt($now)))
            ->values();
    }

    /**
     * Cooks a meal over a stretch of time, through the stretches between boosts running out
     * @return Carbon|null When it got done, or null when it is still cooking at the end
     */
    private function cook(Meal $meal, Carbon $from, Carbon $to, Collection $boosts): ?Carbon
    {
        $work = (float) $meal->Recipe->cooking_time;
        $points = [...$this->getBoundaries($boosts, $from, $to), $to];
        $at = $from->copy();

        foreach ($points as $until) {
            $speed = $this->getMealSpeed($meal, EquipmentStage::COOKING, $at, $boosts);
            $needed = ($work - $meal->cooking_progress) / $speed;
            $available = ($until->getTimestamp() - $at->getTimestamp()) / 60;

            if ($needed <= $available) {
                $meal->cooking_progress = $work;
                return $at->copy()->addSeconds((int) round($needed * 60));
            }

            $meal->cooking_progress = round($meal->cooking_progress + $available * $speed, 4);
            $at = $until->copy();
        }

        $meal->cooking_progress = round(min($work, $meal->cooking_progress), 2);
        return null;
    }

    /**
     * Works out when a cooking meal will be done from a moment on, as the boosts it has run out
     * @return Carbon When it will be done
     */
    private function project(Meal $meal, Carbon $from, Collection $boosts): Carbon
    {
        $left = max(0.0, (float) $meal->Recipe->cooking_time - $meal->cooking_progress);
        $at = $from->copy();

        foreach ([...$this->getBoundaries($boosts, $from, null), null] as $until) {
            $speed = $this->getMealSpeed($meal, EquipmentStage::COOKING, $at, $boosts);
            $available = $until !== null ? ($until->getTimestamp() - $at->getTimestamp()) / 60 : INF;

            if ($left / $speed <= $available) {
                return $at->copy()->addSeconds((int) ceil($left / $speed * 60));
            }

            $left -= $available * $speed;
            $at = $until->copy();
        }

        return $at;
    }

    /**
     * Sets when a cooking meal is done, after which it stays good for its recipe's good for time. Does not save
     */
    private function schedule(Meal $meal, Carbon $doneAt): void
    {
        $meal->cooking_ends_at = $doneAt;
        $meal->expires_at = $doneAt->copy()->addHours($meal->Recipe->good_for);
    }
}
