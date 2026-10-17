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
use App\Models\DnD\Restaurant\Work;
use App\Service\DnD\Restaurant\ShiftService;
use Illuminate\Testing\TestResponse;
use Tests\Feature\AbstractFeatureTester;
use Tests\Traits\DnD\RestaurantTestHelper;

/**
 * Covers boosts: configured on the manage page and activated during service on any of their targets, they add to the
 * speed of a cooker, equipment, a meal or an area for a while, do minutes of work straight away, and improve a meal's
 * quality and how long it stays fresh. Equipment multipliers count towards speed as well. The stew preps in 10 minutes,
 * cooks in 20 and stays fresh for 30 once served, and the service starts at 12:00
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

    /** @var array<string, string[]> The targets of the kinds of boost there were before a boost could have several */
    private const array TARGET_GROUPS = [
        'equipment' => [Boost::TARGET_COOKER, Boost::TARGET_EQUIPMENT],
        'meal'      => [Boost::TARGET_PREPPED_MEAL, Boost::TARGET_COOKING_MEAL],
        'area'      => [Boost::TARGET_PREP_AREA, Boost::TARGET_COOKING_AREA, Boost::TARGET_CLEANING_AREA],
    ];

    /**
     * Creates a boost named Haste
     * @param string|string[] $targets Its targets, or one of the TARGET_GROUPS for all of those
     */
    private function createBoost(string|array $targets, float $speed = 0, int $duration = 0, float $minutesOff = 0, int $quality = 0, int $freshFor = 0): Boost
    {
        $boost = new Boost();
        $boost->restaurant_uuid = $this->restaurant->uuid;
        $boost->name = 'Haste';
        $boost->targets = is_string($targets) ? self::TARGET_GROUPS[$targets] : $targets;
        $boost->fresh_for_bonus = $freshFor;
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
            'name' => 'Sharpened knives', 'targets' => ['equipment', 'cooker'], 'speed_bonus' => 0.5, 'duration' => 30, 'minutes_off' => 2,
            'quality_bonus' => 4, 'fresh_for_bonus' => 10,
        ]);

        $response->assertCreated();
        // Only meals get better, so boosts without a meal target leave quality and freshness alone. Targets are kept in
        // the order they are offered in
        $this->assertEquals(['name' => 'Sharpened knives', 'targets' => ['cooker', 'equipment'], 'speed_bonus' => 0.5, 'minutes_off' => 2,
            'quality_bonus' => 0, 'fresh_for_bonus' => 0, 'duration' => 30],
            array_diff_key(collect($response->json('data.floor.boosts'))->firstWhere('uuid', $response->json('data.uuid')), ['uuid' => 0]));

        $uuid = $response->json('data.uuid');
        $this->callService('api.dnd.restaurant.floor.boost.edit', [
            'name' => 'Blessing', 'targets' => ['prepped_meal', 'planned_meal'], 'speed_bonus' => 0, 'duration' => 0, 'minutes_off' => 0,
            'quality_bonus' => 4, 'fresh_for_bonus' => 15,
        ], ['boost_uuid' => $uuid])->assertOk();
        $boost = Boost::find($uuid);
        $this->assertEquals(['planned_meal', 'prepped_meal'], $boost->targets);
        $this->assertEquals(4, $boost->quality_bonus);
        $this->assertEquals(15, $boost->fresh_for_bonus);

        $this->callService('api.dnd.restaurant.floor.boost.delete', [], ['boost_uuid' => $uuid])->assertOk();
        $this->assertNull(Boost::find($uuid));
    }

    public function test_a_speed_bonus_needs_a_duration(): void
    {
        $response = $this->callService('api.dnd.restaurant.floor.boost.create', [
            'name' => 'Haste', 'targets' => ['prep_area'], 'speed_bonus' => 1, 'duration' => 0, 'minutes_off' => 0,
        ]);

        $response->assertBadRequest();
        $this->assertEquals('A speed bonus needs a duration', $response->json('message'));
    }

    public function test_a_boost_needs_at_least_one_known_target(): void
    {
        foreach ([[], ['nowhere'], ['cooker', 'cooker']] as $targets) {
            $this->callService('api.dnd.restaurant.floor.boost.create', ['name' => 'Haste', 'targets' => $targets, 'speed_bonus' => 0, 'duration' => 0, 'minutes_off' => 0])
                ->assertBadRequest();
        }
        $this->callService('api.dnd.restaurant.floor.boost.create', ['name' => 'Haste', 'speed_bonus' => 0, 'duration' => 0, 'minutes_off' => 0])
            ->assertBadRequest();

        $this->assertEquals(0, Boost::where('restaurant_uuid', $this->restaurant->uuid)->count());
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

    public function test_a_boost_adds_to_what_the_equipment_gives(): void
    {
        // The stove alone makes it 200%, so the stew cooks in 10 minutes. A boost of 10% adds to that, making it 210%
        $this->stove->multiplier = 2;
        $this->stove->save();
        $meal = $this->createPreppedMeal();
        $this->cook($meal);

        $response = $this->activate($this->createBoost('equipment', 0.1, 60), ['equipment_uuid' => $this->stove->uuid, 'cooker_slot' => 1])->assertOk();

        $this->assertEquals(2.1, collect($response->json('data.meals'))->firstWhere('uuid', $meal->uuid)['speed']);
        // 20 minutes of cooking at 210% takes 9 minutes and 32 seconds rather than 10 minutes
        $this->assertEquals($this->restaurant->fresh()->getNow()->addSeconds(572)->toDateTimeString(), $meal->fresh()->cooking_ends_at->toDateTimeString());
    }

    public function test_the_time_left_is_rounded_to_the_nearest_minute(): void
    {
        // 30 minutes of cooking at 110% takes 27 minutes and 16 seconds, which shows as 27 rather than 28
        $this->stew->cooking_time = 30;
        $this->stew->save();
        $meal = $this->createPreppedMeal();
        $this->cook($meal);

        $response = $this->activate($this->createBoost('equipment', 0.1, 60), ['equipment_uuid' => $this->stove->uuid, 'cooker_slot' => 1])->assertOk();

        $this->assertEquals(27, collect($response->json('data.meals'))->firstWhere('uuid', $meal->uuid)['cooking_minutes_left']);
    }

    public function test_time_left_shows_at_least_a_minute_until_it_is_done(): void
    {
        $this->assertEquals(1, \App\Service\DnD\Restaurant\Minutes::display(0.2));
        $this->assertEquals(0, \App\Service\DnD\Restaurant\Minutes::display(0));
        $this->assertEquals(0, \App\Service\DnD\Restaurant\Minutes::display(-3));
        $this->assertEquals(28, \App\Service\DnD\Restaurant\Minutes::display(27.5));
    }

    public function test_boost_bonuses_add_up_with_the_equipment(): void
    {
        $this->stove->multiplier = 2;
        $this->stove->save();
        $meal = $this->createPreppedMeal();
        $this->cook($meal);

        $this->activate($this->createBoost('equipment', 0.1, 60), ['equipment_uuid' => $this->stove->uuid, 'cooker_slot' => 1])->assertOk();
        $response = $this->activate($this->createBoost('meal', 0.25, 60), ['meal_uuid' => $meal->uuid])->assertOk();

        // 200% from the stove, plus 10% and 25% from the boosts
        $this->assertEquals(2.35, collect($response->json('data.meals'))->firstWhere('uuid', $meal->uuid)['speed']);
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

    //| freshness

    public function test_a_cooked_meal_stays_fresh_longer_once_served(): void
    {
        $meal = $this->createPreppedMeal();
        $this->cook($meal);
        $this->advance(20);
        $this->activate($this->createBoost([Boost::TARGET_COOKED_MEAL], freshFor: 15), ['meal_uuid' => $meal->uuid])->assertOk();

        $this->callService('api.dnd.restaurant.service.meal.serve', [], ['meal_uuid' => $meal->uuid])->assertOk();

        // Fresh for its 30 minutes, and the 15 the boost added
        $now = $this->restaurant->fresh()->getNow();
        $this->assertEquals($now->copy()->addMinutes(45)->toDateTimeString(), $meal->fresh()->expires_at->toDateTimeString());
    }

    public function test_a_served_meal_spoils_later_straight_away(): void
    {
        $meal = $this->createPreppedMeal();
        $this->cook($meal);
        $this->advance(20);
        $this->callService('api.dnd.restaurant.service.meal.serve', [], ['meal_uuid' => $meal->uuid])->assertOk();
        $expires = $meal->fresh()->expires_at;

        $this->activate($this->createBoost([Boost::TARGET_COOKED_MEAL], freshFor: 10), ['meal_uuid' => $meal->uuid])->assertOk();

        $this->assertEquals($expires->copy()->addMinutes(10)->toDateTimeString(), $meal->fresh()->expires_at->toDateTimeString());
    }

    //| meals by how far along they are

    public function test_cooking_and_cooked_meals_are_told_apart(): void
    {
        $meal = $this->createPreppedMeal();
        $this->cook($meal);
        $cooking = $this->createBoost([Boost::TARGET_COOKING_MEAL], quality: 1);
        $cooked = $this->createBoost([Boost::TARGET_COOKED_MEAL], quality: 1);

        // While it cooks, only a boost for cooking meals takes
        $response = $this->activate($cooked, ['meal_uuid' => $meal->uuid]);
        $response->assertBadRequest();
        $this->assertEquals("Haste can't be used on a cooking meal", $response->json('message'));
        $this->activate($cooking, ['meal_uuid' => $meal->uuid])->assertOk();

        // Once it is done, only a boost for cooked meals does
        $this->advance(20);
        $this->activate($cooking, ['meal_uuid' => $meal->uuid])->assertBadRequest();
        $this->activate($cooked, ['meal_uuid' => $meal->uuid])->assertOk();
        $this->assertEquals(2, $meal->fresh()->quality_bonus);
    }

    public function test_a_planned_meal_gets_prep_done_straight_away(): void
    {
        $meal = $this->createTestMeal($this->restaurant, $this->stew);
        $this->callService('api.dnd.restaurant.service.meal.prepare', [], ['meal_uuid' => $meal->uuid])->assertOk();

        $this->activate($this->createBoost([Boost::TARGET_PLANNED_MEAL], minutesOff: 4), ['meal_uuid' => $meal->uuid])->assertOk();

        $this->assertEquals(6, Work::where('meal_uuid', $meal->uuid)->first()->getRemaining());
    }

    //| activating

    //| stacking

    public function test_the_same_boost_does_not_stack_on_the_same_thing(): void
    {
        $boost = $this->createBoost('equipment', 1, 60);
        $this->activate($boost, ['equipment_uuid' => $this->stove->uuid, 'cooker_slot' => 1])->assertOk();

        $response = $this->activate($boost, ['equipment_uuid' => $this->stove->uuid, 'cooker_slot' => 1]);
        $response->assertBadRequest();
        $this->assertEquals('Haste is already active on Stove 1', $response->json('message'));

        // It still goes on something else, and another boost still stacks on the same cooker
        $this->activate($boost, ['equipment_uuid' => $this->stove->uuid])->assertOk();
        $this->activate($this->createBoost('equipment', 1, 60), ['equipment_uuid' => $this->stove->uuid, 'cooker_slot' => 1])->assertOk();
        $this->assertEquals(3, ActiveBoost::where('restaurant_uuid', $this->restaurant->uuid)->count());
    }

    public function test_a_boost_can_be_activated_again_once_it_ran_out(): void
    {
        $boost = $this->createBoost('area', 1, 10);
        $this->activate($boost, ['area' => 'prep'])->assertOk();
        $this->advance(10);

        $this->activate($boost, ['area' => 'prep'])->assertOk();
    }

    //| cancelling

    public function test_a_boost_can_be_cancelled(): void
    {
        // Cooking twice as fast for 5 minutes does 10 of the 20 minutes, after which the rest takes 10 more
        $meal = $this->createPreppedMeal();
        $this->cook($meal);
        $response = $this->activate($this->createBoost('area', 1, 60), ['area' => 'cooking'])->assertOk();
        $active = $response->json('data.active_boosts.0.uuid');
        $this->advance(5);

        $response = $this->callService('api.dnd.restaurant.service.boost.cancel', [], ['active_boost_uuid' => $active])->assertOk();

        $this->assertEquals([], $response->json('data.active_boosts'));
        $described = collect($response->json('data.meals'))->firstWhere('uuid', $meal->uuid);
        $this->assertEquals(1, $described['speed']);
        $this->assertEquals(10, $described['cooking_minutes_left']);
        $this->assertEquals(10, $meal->fresh()->cooking_progress);

        // It can't be cancelled twice, and can be activated again straight away
        $this->callService('api.dnd.restaurant.service.boost.cancel', [], ['active_boost_uuid' => $active])->assertBadRequest();
        $this->assertEquals('Haste has already run out', $this->callService('api.dnd.restaurant.service.boost.cancel', [], ['active_boost_uuid' => $active])->json('message'));
    }

    public function test_cancelling_a_boost_of_another_restaurant_is_not_found(): void
    {
        $other = $this->createTestRestaurant($this->getAdminUser(), 'Other');
        $active = new ActiveBoost();
        $active->restaurant_uuid = $other->uuid;
        $active->name = 'Haste';
        $active->speed_bonus = 1;
        $active->area = 'prep';
        $active->starts_at = now();
        $active->expires_at = now()->addHour();
        $active->save();

        $this->callService('api.dnd.restaurant.service.boost.cancel', [], ['active_boost_uuid' => $active->uuid])->assertNotFound();
    }

    public function test_a_boost_is_only_activated_on_its_targets(): void
    {
        $knife = $this->createTestEquipment($this->restaurant, 1, 'Knife', false);
        $this->ownEquipment($this->restaurant, $knife, 1);
        $boost = $this->createBoost([Boost::TARGET_COOKER], 1, 10);

        $response = $this->activate($boost, ['equipment_uuid' => $knife->uuid]);
        $response->assertBadRequest();
        $this->assertEquals("Haste can't be used on equipment", $response->json('message'));
        $this->activate($boost, ['area' => 'cooking'])->assertBadRequest();

        $this->activate($boost, ['equipment_uuid' => $this->stove->uuid, 'cooker_slot' => 1])->assertOk();
    }

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
