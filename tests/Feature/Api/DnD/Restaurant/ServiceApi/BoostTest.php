<?php

namespace Tests\Feature\Api\DnD\Restaurant\ServiceApi;

use App\Enum\DnD\Restaurant\WorkStation;
use App\Models\DnD\Restaurant\ActiveBoost;
use App\Models\DnD\Restaurant\Boost;
use App\Models\DnD\Restaurant\Equipment;
use App\Models\DnD\Restaurant\Meal;
use App\Models\DnD\Restaurant\Recipe;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\ServedMeal;
use App\Service\DnD\Restaurant\ShiftService;
use Illuminate\Testing\TestResponse;
use Tests\Feature\AbstractFeatureTester;
use Tests\Traits\DnD\RestaurantTestHelper;

/**
 * Covers boosts: configured on the manage page and activated during service, they add to the speed of equipment,
 * a meal or an area for a while, do minutes of work straight away, and improve a meal's quality. Equipment multipliers
 * count towards speed as well. The stew preps in 10 minutes and cooks in 20, and the service starts at 12:00
 */
class BoostTest extends AbstractFeatureTester
{
    use RestaurantTestHelper;

    private Restaurant $restaurant;
    private Recipe $stew;
    private Equipment $stove;

    public function setUp(): void
    {
        parent::setUp();
        $this->restaurant = $this->createTestRestaurant($this->getAdminUser());
        app(ShiftService::class)->startService($this->restaurant, '12:00');
        $this->restaurant->refresh();

        $this->stew = $this->createTestRecipe($this->restaurant, 'Stew');
        $this->stove = $this->createTestCooker($this->restaurant);
    }

    private function callService(string $route, array $parameters = [], array $routeParameters = []): TestResponse
    {
        return $this->withHeaders(['Accept' => 'application/json'])->actingAs($this->getAdminUser())
            ->postJson(route($route, array_merge(['restaurant_uuid' => $this->restaurant->uuid], $routeParameters)), $parameters);
    }

    private function createBoost(string $target, float $speed = 0, int $duration = 0, float $minutesOff = 0, int $quality = 0): Boost
    {
        $boost = new Boost();
        $boost->restaurant_uuid = $this->restaurant->uuid;
        $boost->name = 'Haste';
        $boost->target = $target;
        $boost->speed_bonus = $speed;
        $boost->duration = $duration;
        $boost->minutes_off = $minutesOff;
        $boost->quality_bonus = $quality;
        $boost->save();

        return $boost;
    }

    private function activate(Boost $boost, array $target): TestResponse
    {
        return $this->callService('api.dnd.restaurant.service.boost.activate', $target, ['boost_uuid' => $boost->uuid]);
    }

    private function advance(int $minutes): TestResponse
    {
        return $this->callService('api.dnd.restaurant.service.time', ['advance' => $minutes])->assertOk();
    }

    private function createPreppedMeal(array $equipment = []): Meal
    {
        $meal = $this->createTestMeal($this->restaurant, $this->stew, Meal::STATUS_PREPPED);
        $meal->portions_left = 1;
        $meal->equipment = $equipment;
        $meal->save();

        return $meal;
    }

    private function cook(Meal $meal): void
    {
        $this->callService('api.dnd.restaurant.service.meal.cook', ['cooker_uuid' => $this->stove->uuid, 'slot' => 1], ['meal_uuid' => $meal->uuid])->assertOk();
    }

    private function getStatus(Meal $meal): string
    {
        return $meal->fresh()->getStatusAt($this->restaurant->fresh()->getNow());
    }

    //| configuring

    public function test_boosts_are_configured_on_the_manage_page(): void
    {
        $response = $this->callService('api.dnd.restaurant.floor.boost.create', [
            'name' => 'Sharpened knives', 'target' => 'equipment', 'speed_bonus' => 0.5, 'duration' => 30, 'minutes_off' => 2, 'quality_bonus' => 4,
        ]);

        $response->assertCreated();
        // Only meals get better, so equipment boosts leave quality alone
        $this->assertEquals(['name' => 'Sharpened knives', 'target' => 'equipment', 'speed_bonus' => 0.5, 'minutes_off' => 2, 'quality_bonus' => 0, 'duration' => 30],
            array_diff_key(collect($response->json('data.floor.boosts'))->firstWhere('uuid', $response->json('data.uuid')), ['uuid' => 0]));

        $uuid = $response->json('data.uuid');
        $this->callService('api.dnd.restaurant.floor.boost.edit', [
            'name' => 'Blessing', 'target' => 'meal', 'speed_bonus' => 0, 'duration' => 0, 'minutes_off' => 0, 'quality_bonus' => 4,
        ], ['boost_uuid' => $uuid])->assertOk();
        $this->assertEquals(4, Boost::find($uuid)->quality_bonus);

        $this->callService('api.dnd.restaurant.floor.boost.delete', [], ['boost_uuid' => $uuid])->assertOk();
        $this->assertNull(Boost::find($uuid));
    }

    public function test_a_speed_bonus_needs_a_duration(): void
    {
        $response = $this->callService('api.dnd.restaurant.floor.boost.create', [
            'name' => 'Haste', 'target' => 'area', 'speed_bonus' => 1, 'duration' => 0, 'minutes_off' => 0,
        ]);

        $response->assertBadRequest();
        $this->assertEquals('A speed bonus needs a duration', $response->json('message'));
        $this->callService('api.dnd.restaurant.floor.boost.create', ['name' => 'Haste', 'target' => 'nowhere', 'speed_bonus' => 0, 'duration' => 0, 'minutes_off' => 0])
            ->assertBadRequest();
    }

    //| equipment multipliers

    public function test_a_cookers_multiplier_speeds_up_cooking(): void
    {
        $this->stove->multiplier = 2;
        $this->stove->save();
        $meal = $this->createPreppedMeal();

        $this->cook($meal);
        $this->advance(9);
        $this->assertEquals(Meal::STATUS_COOKING, $this->getStatus($meal));

        $this->advance(1);
        $this->assertEquals(Meal::STATUS_DONE, $this->getStatus($meal));
    }

    public function test_prep_equipments_multiplier_speeds_up_prep(): void
    {
        $knife = $this->createTestEquipment($this->restaurant, 1, 'Knife');
        $knife->multiplier = 2;
        $knife->save();
        $this->createTestEmployee($this->restaurant, WorkStation::PREP);
        $meal = $this->createTestMeal($this->restaurant, $this->stew);
        $meal->equipment = [['equipment_uuid' => $knife->uuid, 'amount' => 1, 'stage' => 'prep', 'container' => false]];
        $meal->save();

        $this->callService('api.dnd.restaurant.service.meal.prepare', [], ['meal_uuid' => $meal->uuid])->assertOk();
        $this->advance(5);

        $this->assertEquals(Meal::STATUS_PREPPED, $meal->fresh()->status);
    }

    //| speed bonuses

    public function test_progress_made_while_boosted_is_kept_once_the_boost_runs_out(): void
    {
        // Twice as fast for 5 minutes does 10 minutes of cooking, leaving 10 at normal speed
        $meal = $this->createPreppedMeal();
        $this->cook($meal);
        $response = $this->activate($this->createBoost('area', 1, 5), ['area' => 'cooking'])->assertOk();
        $this->assertEquals(15, collect($response->json('data.meals'))->firstWhere('uuid', $meal->uuid)['cooking_minutes_left']);
        $this->assertEquals(2, collect($response->json('data.meals'))->firstWhere('uuid', $meal->uuid)['speed']);

        $this->advance(14);
        $this->assertEquals(Meal::STATUS_COOKING, $this->getStatus($meal));
        $this->assertEquals(19, $meal->fresh()->cooking_progress);

        $this->advance(1);
        $this->assertEquals(Meal::STATUS_DONE, $this->getStatus($meal));
    }

    public function test_boosts_add_up(): void
    {
        // A boost on the cooker and one on the meal make it go three times as fast
        $meal = $this->createPreppedMeal();
        $this->cook($meal);
        $this->activate($this->createBoost('equipment', 1, 60), ['equipment_uuid' => $this->stove->uuid])->assertOk();
        $response = $this->activate($this->createBoost('meal', 1, 60), ['meal_uuid' => $meal->uuid])->assertOk();

        $this->assertEquals(3, collect($response->json('data.meals'))->firstWhere('uuid', $meal->uuid)['speed']);
        $this->assertCount(2, $response->json('data.active_boosts'));
        $this->advance(7);
        $this->assertEquals(Meal::STATUS_DONE, $this->getStatus($meal));
    }

    public function test_the_prep_area_can_be_boosted(): void
    {
        $this->createTestEmployee($this->restaurant, WorkStation::PREP);
        $meal = $this->createTestMeal($this->restaurant, $this->stew);
        $this->callService('api.dnd.restaurant.service.meal.prepare', [], ['meal_uuid' => $meal->uuid])->assertOk();

        $this->activate($this->createBoost('area', 1, 60), ['area' => 'prep'])->assertOk();
        $this->advance(5);

        $this->assertEquals(Meal::STATUS_PREPPED, $meal->fresh()->status);
    }

    //| straight away

    public function test_minutes_off_cook_a_meal_on_straight_away(): void
    {
        $meal = $this->createPreppedMeal();
        $this->cook($meal);

        $this->activate($this->createBoost('meal', minutesOff: 15), ['meal_uuid' => $meal->uuid])->assertOk();
        $this->assertEquals(15, $meal->fresh()->cooking_progress);

        $this->advance(5);
        $this->assertEquals(Meal::STATUS_DONE, $this->getStatus($meal));
    }

    public function test_minutes_off_give_a_prepped_meal_a_head_start(): void
    {
        $meal = $this->createPreppedMeal();
        $this->activate($this->createBoost('meal', minutesOff: 5), ['meal_uuid' => $meal->uuid])->assertOk();

        $this->cook($meal);
        $this->advance(14);
        $this->assertEquals(Meal::STATUS_COOKING, $this->getStatus($meal));
        $this->advance(1);
        $this->assertEquals(Meal::STATUS_DONE, $this->getStatus($meal));
    }

    public function test_minutes_off_can_finish_prep_straight_away(): void
    {
        $meal = $this->createTestMeal($this->restaurant, $this->stew);
        $this->callService('api.dnd.restaurant.service.meal.prepare', [], ['meal_uuid' => $meal->uuid])->assertOk();

        $this->activate($this->createBoost('area', minutesOff: 10), ['area' => 'prep'])->assertOk();

        $this->assertEquals(Meal::STATUS_PREPPED, $meal->fresh()->status);
    }

    //| quality

    public function test_a_meals_quality_bonus_is_served_with_it(): void
    {
        $meal = $this->createPreppedMeal();
        $this->activate($this->createBoost('meal', quality: 3), ['meal_uuid' => $meal->uuid])->assertOk();
        $this->cook($meal);
        $this->advance(20);

        $response = $this->callService('api.dnd.restaurant.service.meal.quality', ['quality' => 10], ['meal_uuid' => $meal->uuid])->assertOk();
        $this->assertEquals(['quality' => 13, 'quality_bonus' => 3],
            array_intersect_key(collect($response->json('data.meals'))->firstWhere('uuid', $meal->uuid), ['quality' => 0, 'quality_bonus' => 0]));

        $this->callService('api.dnd.restaurant.service.meal.plate', [], ['meal_uuid' => $meal->uuid])->assertOk();
        $this->assertEquals(13, ServedMeal::where('restaurant_uuid', $this->restaurant->uuid)->value('quality'));
    }

    //| activating

    public function test_boosts_are_only_activated_on_what_they_are_for(): void
    {
        $meal = $this->createPreppedMeal();
        $planned = $this->createTestMeal($this->restaurant, $this->stew);

        $this->activate($this->createBoost('meal', 1, 10), [])->assertBadRequest();
        $this->activate($this->createBoost('meal', 1, 10), ['meal_uuid' => $planned->uuid])->assertBadRequest();
        $this->activate($this->createBoost('area', 1, 10), ['area' => 'dining room'])->assertBadRequest();
        $this->activate($this->createBoost('equipment', 1, 10), ['meal_uuid' => $meal->uuid])->assertBadRequest();
        $this->assertEquals(0, ActiveBoost::where('restaurant_uuid', $this->restaurant->uuid)->count());
    }

    //| single cookers

    public function test_a_boost_on_a_single_cooker_only_speeds_up_the_meal_in_it(): void
    {
        $oven = $this->createTestCooker($this->restaurant, 2, 'Oven');
        $first = $this->createPreppedMeal();
        $second = $this->createPreppedMeal();
        foreach ([1 => $first, 2 => $second] as $slot => $meal) {
            $this->callService('api.dnd.restaurant.service.meal.cook', ['cooker_uuid' => $oven->uuid, 'slot' => $slot], ['meal_uuid' => $meal->uuid])->assertOk();
        }

        $response = $this->activate($this->createBoost('equipment', 1, 60, 5), ['equipment_uuid' => $oven->uuid, 'cooker_slot' => 2])->assertOk();

        // Only the second oven goes twice as fast, and only its meal got the minutes off
        $meals = collect($response->json('data.meals'));
        $this->assertEquals([1, 2], [$meals->firstWhere('uuid', $first->uuid)['speed'], $meals->firstWhere('uuid', $second->uuid)['speed']]);
        $this->assertEquals([0, 5], [$first->fresh()->cooking_progress, $second->fresh()->cooking_progress]);
        $this->assertEquals('Oven 2', $response->json('data.active_boosts.0.target_name'));
    }

    public function test_only_existing_cookers_can_be_boosted_singly(): void
    {
        $plate = $this->createTestEquipment($this->restaurant, 1, 'Plate');
        $boost = $this->createBoost('equipment', 1, 60);

        $this->activate($boost, ['equipment_uuid' => $this->stove->uuid, 'cooker_slot' => 2])->assertBadRequest();
        $this->activate($boost, ['equipment_uuid' => $plate->uuid, 'cooker_slot' => 1])->assertBadRequest();
        $this->assertEquals(0, ActiveBoost::where('restaurant_uuid', $this->restaurant->uuid)->count());
    }

    //| cleaning

    /** A dirty pot that takes 5 minutes to clean, with someone cleaning */
    private function createDirtyPot(int $dirty): Equipment
    {
        $pot = $this->createTestEquipment($this->restaurant, 1, 'Pot', false);
        $this->ownEquipment($this->restaurant, $pot, $dirty);
        $pot->amount_dirty = $dirty;
        $pot->save();
        $this->createTestEmployee($this->restaurant, WorkStation::CLEANING);

        return $pot;
    }

    public function test_the_cleaning_area_can_be_boosted(): void
    {
        $pot = $this->createDirtyPot(1);
        $this->callService('api.dnd.restaurant.service.equipment.clean', ['amount' => 1], ['equipment_uuid' => $pot->uuid])->assertOk();

        // Twice as fast for 2 minutes cleans 4 minutes' worth, leaving 1 at normal speed
        $this->activate($this->createBoost('area', 1, 2), ['area' => 'cleaning'])->assertOk();
        $this->advance(2);
        $this->assertEquals(1, $pot->fresh()->amount_dirty);

        $this->advance(1);
        $this->assertEquals(0, $pot->fresh()->amount_dirty);
    }

    public function test_minutes_off_clean_pieces_straight_away(): void
    {
        $pot = $this->createDirtyPot(2);
        $this->callService('api.dnd.restaurant.service.equipment.clean', ['amount' => 2], ['equipment_uuid' => $pot->uuid])->assertOk();

        $this->activate($this->createBoost('area', minutesOff: 5), ['area' => 'cleaning'])->assertOk();

        $this->assertEquals(0, $pot->fresh()->amount_dirty);
    }

    public function test_boosts_last_for_the_service_they_were_activated_in(): void
    {
        $this->activate($this->createBoost('area', 1, 600), ['area' => 'cooking'])->assertOk();

        $this->callService('api.dnd.restaurant.service.end')->assertOk();

        $this->assertEquals(0, ActiveBoost::where('restaurant_uuid', $this->restaurant->uuid)->count());
        $this->activate($this->createBoost('area', 1, 10), ['area' => 'cooking'])->assertBadRequest();
    }
}
