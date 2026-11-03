<?php

namespace Tests\Feature\Api\DnD\Restaurant\ServiceApi;

use App\Enum\DnD\Restaurant\EquipmentStage;
use App\Enum\DnD\Restaurant\WorkStation;
use App\Models\DnD\Restaurant\Employee;
use App\Models\DnD\Restaurant\Equipment;
use App\Models\DnD\Restaurant\Meal;
use App\Models\DnD\Restaurant\Recipe;
use App\Models\DnD\Restaurant\Restaurant;
use App\Service\DnD\Restaurant\SatisfactionService;
use App\Service\DnD\Restaurant\ShiftService;
use Illuminate\Testing\TestResponse;
use ReflectionMethod;
use Tests\Feature\AbstractFeatureTester;
use Tests\Traits\DnD\RestaurantTestHelper;

/**
 * Covers cooking and the quality it adds: meals cook with or without a cook, though the quality suffers for the share
 * of the cooking nobody looked after the cookers, by up to 20 points. A cook busy prepping counts for a quarter of
 * that, and the best cook available adds their cooking quality over the time they looked after them. The stew cooks in
 * 20 minutes and preps in 10, and the service starts at 12:00
 */
class CookingAreaTest extends AbstractFeatureTester
{
    use RestaurantTestHelper;

    private Restaurant $restaurant;
    private Recipe $stew;
    private Equipment $stove;
    private Employee $chef;

    public function setUp(): void
    {
        parent::setUp();
        $this->restaurant = $this->createTestRestaurant($this->getAdminUser());
        app(ShiftService::class)->startService($this->restaurant, '12:00');
        $this->restaurant->refresh();

        $this->stew = $this->createTestRecipe($this->restaurant, 'Stew');
        $this->stove = $this->createTestCooker($this->restaurant);
        $this->chef = $this->createTestEmployee($this->restaurant, null, 'Chef');
    }

    private function callService(string $route, array $parameters = [], array $routeParameters = []): TestResponse
    {
        return $this->withHeaders(['Accept' => 'application/json'])->actingAs($this->getAdminUser())
            ->postJson(route('api.dnd.restaurant.service.' . $route, array_merge(['restaurant_uuid' => $this->restaurant->uuid], $routeParameters)), $parameters);
    }

    private function cookStew(): Meal
    {
        $meal = $this->createTestMeal($this->restaurant, $this->stew, Meal::STATUS_PREPPED);
        $this->callService('meal.cook', ['cooker_uuid' => $this->stove->uuid, 'slot' => 1], ['meal_uuid' => $meal->uuid])->assertOk();
        return $meal;
    }

    private function assign(Employee $employee, ?string ...$stations): void
    {
        $this->callService('employee.assign', array_filter(['station' => $stations[0] ?? null]), ['employee_uuid' => $employee->uuid])->assertOk();
        if (isset($stations[1])) {
            $this->callService('employee.assign', ['station' => $stations[1], 'combine' => true], ['employee_uuid' => $employee->uuid])->assertOk();
        }
    }

    private function advance(int $minutes): void
    {
        $this->callService('time', ['advance' => $minutes])->assertOk();
    }

    private function hireCook(string $name, int $cookingQuality): Employee
    {
        $cook = $this->createTestEmployee($this->restaurant, WorkStation::COOKING, $name);
        $cook->cooking_quality = $cookingQuality;
        $cook->save();
        return $cook;
    }

    private function getStatus(Meal $meal): string
    {
        return $meal->fresh()->getStatusAt($this->restaurant->fresh()->getNow());
    }

    //| cooking without a cook

    public function test_meals_cook_without_anyone_in_the_cooking_area(): void
    {
        $meal = $this->cookStew();

        $this->advance(20);

        $this->assertEquals(Meal::STATUS_DONE, $this->getStatus($meal));
    }

    public function test_cooking_without_a_cook_costs_the_whole_penalty(): void
    {
        $meal = $this->cookStew();
        $this->advance(20);

        $this->assertEquals(-20, $meal->fresh()->getCookingQuality());
    }

    public function test_the_penalty_follows_how_long_nobody_was_there(): void
    {
        $meal = $this->cookStew();
        $this->advance(5);
        $this->assign($this->chef, 'cooking');
        $this->advance(15);

        // A quarter of the time nobody was there: -5, and the cook without any skill adds nothing
        $this->assertEquals(Meal::STATUS_DONE, $this->getStatus($meal));
        $this->assertEquals(-5, $meal->fresh()->getCookingQuality());
    }

    public function test_a_meal_nobody_cooks_can_be_skipped(): void
    {
        $meal = $this->cookStew();

        $this->callService('meal.skip', [], ['meal_uuid' => $meal->uuid])->assertOk();

        $this->assertEquals(Meal::STATUS_DONE, $this->getStatus($meal));
    }

    //| the cook's skill

    public function test_a_cook_adds_their_cooking_quality_over_the_time_they_cook(): void
    {
        $this->hireCook('Remy', 10);
        $meal = $this->cookStew();

        $this->advance(20);

        $this->assertEquals(10, $meal->fresh()->getCookingQuality());
    }

    public function test_the_skill_only_counts_for_the_time_the_cook_was_there(): void
    {
        $remy = $this->createTestEmployee($this->restaurant, null, 'Remy');
        $remy->cooking_quality = 10;
        $remy->save();
        $meal = $this->cookStew();

        $this->advance(10);
        $this->assign($remy, 'cooking');
        $this->advance(10);

        // Half the time nobody was there: -10, and half the time the cook added 10: +5
        $this->assertEquals(-5, $meal->fresh()->getCookingQuality());
    }

    public function test_the_best_cook_counts(): void
    {
        $this->hireCook('Remy', 10);
        $this->hireCook('Linguini', 4);
        $meal = $this->cookStew();

        $this->advance(20);

        $this->assertEquals(10, $meal->fresh()->getCookingQuality());
    }

    //| a cook busy prepping

    public function test_a_cook_busy_prepping_only_adds_a_quarter_of_the_penalty(): void
    {
        // Prepping at half speed while cooking takes 20 minutes, which the cook is busy for all of
        $this->assign($this->chef, 'prep', 'cooking');
        $prepped = $this->createTestMeal($this->restaurant, $this->stew);
        $this->callService('meal.prepare', [], ['meal_uuid' => $prepped->uuid])->assertOk();
        $meal = $this->cookStew();

        $this->advance(20);

        // A quarter of the full penalty over the whole cooking, and busy cooks add no skill
        $this->assertEquals(-5, $meal->fresh()->getCookingQuality());
    }

    public function test_a_cook_not_busy_prepping_is_there_in_full(): void
    {
        $this->assign($this->chef, 'prep', 'cooking');
        $meal = $this->cookStew();

        $this->advance(20);

        $this->assertEquals(0, $meal->fresh()->getCookingQuality());
    }

    //| served quality

    public function test_the_quality_served_adds_up_what_the_cooking_and_equipment_add(): void
    {
        $meal = $this->cookStew();
        $meal->quality = 10;
        $meal->quality_bonus = 2;
        $meal->ingredient_quality = 1;
        $meal->equipment_bonus = 3;
        $meal->equipment_multiplier = 0.3;
        $meal->cooking_minutes = 20;
        $meal->cooking_absent = 10;

        // 10 + 30% = 13 on the roll, +2 boosts, +1 ingredients, +3 equipment, and -10 for half the cooking unattended
        $this->assertEquals(-10, $meal->getCookingQuality());
        $this->assertEquals(13 + 2 + 1 + 3 - 10, $meal->getServedQuality());
    }

    //| equipment

    public function test_equipment_adds_its_bonus_and_multiplier_to_the_meal_it_cooks(): void
    {
        $pot = $this->createTestEquipment($this->restaurant, 1, 'Pot', false);
        $pot->quality_bonus = 2;
        $pot->quality_multiplier = 0.1;
        $pot->save();
        $ladle = $this->createTestEquipment($this->restaurant, 1, 'Ladle', false);
        $ladle->quality_multiplier = 0.2;
        $ladle->save();
        $plain = $this->createTestEquipment($this->restaurant, 1, 'Spoon', false);
        $this->stove->quality_bonus = 1;
        $this->stove->save();

        $meal = $this->createTestMeal($this->restaurant, $this->stew, Meal::STATUS_PREPPED);
        $meal->equipment = array_map(fn (Equipment $piece) => ['equipment_uuid' => $piece->uuid, 'amount' => 2, 'stage' => EquipmentStage::SERVING->value, 'container' => false],
            [$pot, $ladle, $plain]);
        $meal->save();
        $this->callService('meal.cook', ['cooker_uuid' => $this->stove->uuid, 'slot' => 1], ['meal_uuid' => $meal->uuid])->assertOk();

        // Each piece counts once, whatever the amount, the cooker included, and the multipliers add up: 0.1 + 0.2 + 0
        $this->assertEquals(3, $meal->fresh()->equipment_bonus);
        $this->assertEquals(0.3, $meal->fresh()->equipment_multiplier);
    }

    public function test_the_equipment_fields_are_saved(): void
    {
        $response = $this->withHeaders(['Accept' => 'application/json'])->actingAs($this->getAdminUser())
            ->postJson(route('api.dnd.restaurant.inventory.equipment.create', ['restaurant_uuid' => $this->restaurant->uuid]), [
                'name' => 'Pot', 'category' => 'cookware', 'cleaning_time' => 5, 'multiplier' => 1, 'size' => 1,
                'quality_bonus' => 3, 'quality_multiplier' => 0.15,
            ]);

        $response->assertCreated();
        $pot = Equipment::where('name', 'Pot')->first();
        $this->assertEquals([3, 0.15], [$pot->quality_bonus, $pot->quality_multiplier]);
    }

    //| satisfaction

    private function rateQuality(int $quality, int $difficulty): int
    {
        $this->stew->difficulty = $difficulty;
        $method = new ReflectionMethod(SatisfactionService::class, 'rateQuality');
        return $method->invoke(new SatisfactionService(), $quality, $this->stew);
    }

    public function test_every_5_points_above_the_difficulty_adds_a_satisfaction(): void
    {
        $this->assertEquals(0, $this->rateQuality(14, 10));
        $this->assertEquals(1, $this->rateQuality(15, 10));
        $this->assertEquals(1, $this->rateQuality(19, 10));
        $this->assertEquals(2, $this->rateQuality(20, 10));
        $this->assertEquals(4, $this->rateQuality(30, 10));
    }

    public function test_being_under_the_difficulty_still_costs_satisfaction(): void
    {
        $this->assertEquals(-1, $this->rateQuality(9, 10));
        $this->assertEquals(-1, $this->rateQuality(5, 10));
        $this->assertEquals(-2, $this->rateQuality(4, 10));
    }
}
