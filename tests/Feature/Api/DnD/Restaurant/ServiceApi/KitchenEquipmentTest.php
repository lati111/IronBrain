<?php

namespace Tests\Feature\Api\DnD\Restaurant\ServiceApi;

use App\Enum\DnD\Restaurant\EquipmentStage;
use App\Enum\DnD\Restaurant\WorkStation;
use App\Models\DnD\Restaurant\Equipment;
use App\Models\DnD\Restaurant\Meal;
use App\Models\DnD\Restaurant\Recipe;
use App\Models\DnD\Restaurant\Restaurant;
use Illuminate\Testing\TestResponse;
use Tests\Feature\AbstractFeatureTester;
use Tests\Traits\DnD\RestaurantTestHelper;

/**
 * Covers equipment going in use, becoming dirty and being cleaned as meals go through the kitchen. The test recipe
 * takes 10 minutes to prep and 20 to cook, and test equipment takes 5 minutes to clean per piece. One employee works
 * at prep and one at cleaning, both at normal speed
 */
class KitchenEquipmentTest extends AbstractFeatureTester
{
    use RestaurantTestHelper;

    private Restaurant $restaurant;
    private Recipe $recipe;
    private Equipment $stove;

    public function setUp(): void
    {
        parent::setUp();
        $this->restaurant = $this->createTestRestaurant($this->getAdminUser());
        $this->restaurant->current_time = '08:00';
        $this->restaurant->save();

        $this->recipe = $this->createTestRecipe($this->restaurant, 'Stew');
        $this->stove = $this->createTestCooker($this->restaurant);

        $this->createTestEmployee($this->restaurant, WorkStation::PREP, 'Cook');
        $this->createTestEmployee($this->restaurant, WorkStation::CLEANING, 'Dishwasher');
    }

    /** Creates equipment that doesn't need storage, with a number of pieces owned */
    private function createOwnedEquipment(string $name, int $pieces): Equipment
    {
        $equipment = $this->createTestEquipment($this->restaurant, 1, $name, false);
        $this->ownEquipment($this->restaurant, $equipment, $pieces);
        return $equipment;
    }

    private function callService(string $route, array $parameters = [], array $routeParameters = []): TestResponse
    {
        return $this->withHeaders(['Accept' => 'application/json'])
            ->actingAs($this->getAdminUser())
            ->post(route('api.dnd.restaurant.service.' . $route, array_merge(['restaurant_uuid' => $this->restaurant->uuid], $routeParameters)), $parameters);
    }

    private function plan(): Meal
    {
        $this->callService('meal.plan', ['recipe_uuid' => $this->recipe->uuid])->assertCreated();
        return Meal::where('restaurant_uuid', $this->restaurant->uuid)->latest()->first();
    }

    private function mealAction(string $action, Meal $meal, array $parameters = []): TestResponse
    {
        return $this->callService('meal.' . $action, $parameters, ['meal_uuid' => $meal->uuid]);
    }

    /** Queues a meal for prep, and waits until the prep employee is done with it */
    private function prep(Meal $meal): void
    {
        $this->mealAction('prepare', $meal)->assertOk();
        $this->advance(10);
    }

    private function cook(Meal $meal): TestResponse
    {
        return $this->mealAction('cook', $meal, ['cooker_uuid' => $this->stove->uuid, 'slot' => 1]);
    }

    private function advance(int $minutes): void
    {
        $this->callService('time', ['advance' => $minutes])->assertOk();
    }

    private function assertUsage(Equipment $equipment, int $inUse, int $dirty): void
    {
        $equipment->refresh();
        $this->assertEquals($inUse, $equipment->amount_in_use, $equipment->name . ' in use');
        $this->assertEquals($dirty, $equipment->amount_dirty, $equipment->name . ' dirty');
    }

    //| prep equipment

    public function test_planning_puts_prep_equipment_in_use(): void
    {
        $knife = $this->createOwnedEquipment('Knife', 3);
        $this->addRecipeEquipment($this->recipe, $knife, 2);

        $this->plan();

        $this->assertUsage($knife, 2, 0);
    }

    public function test_planning_needs_enough_clean_equipment(): void
    {
        $knife = $this->createOwnedEquipment('Knife', 2);
        $knife->amount_dirty = 1;
        $knife->save();
        $this->addRecipeEquipment($this->recipe, $knife, 2);

        $response = $this->callService('meal.plan', ['recipe_uuid' => $this->recipe->uuid]);

        $response->assertBadRequest();
        $this->assertEquals('Not enough clean Knife: 2 needed, 1 clean', $response->json('message'));
        $this->assertFalse(Meal::where('restaurant_uuid', $this->restaurant->uuid)->exists());
        $this->assertUsage($knife, 0, 1);
    }

    public function test_prepping_dirties_prep_equipment_but_not_containers(): void
    {
        $knife = $this->createOwnedEquipment('Knife', 1);
        $bowl = $this->createOwnedEquipment('Bowl', 1);
        $this->addRecipeEquipment($this->recipe, $knife, 1);
        $this->addRecipeEquipment($this->recipe, $bowl, 1, EquipmentStage::PREP, true);

        $this->prep($this->plan());

        $this->assertUsage($knife, 0, 1);
        $this->assertUsage($bowl, 1, 0);
    }

    public function test_prep_containers_are_dirty_once_cooking_starts(): void
    {
        $bowl = $this->createOwnedEquipment('Bowl', 1);
        $this->addRecipeEquipment($this->recipe, $bowl, 1, EquipmentStage::PREP, true);
        $meal = $this->plan();
        $this->prep($meal);

        $this->cook($meal)->assertOk();

        $this->assertUsage($bowl, 0, 1);
    }

    //| cooking equipment

    public function test_cooking_equipment_is_used_while_cooking_and_dirty_after(): void
    {
        $pot = $this->createOwnedEquipment('Pot', 1);
        $this->addRecipeEquipment($this->recipe, $pot, 1, EquipmentStage::COOKING);
        $meal = $this->plan();
        $this->assertUsage($pot, 0, 0);
        $this->prep($meal);

        $this->cook($meal)->assertOk();
        $this->assertUsage($pot, 1, 0);

        $this->advance(20);
        $this->assertUsage($pot, 0, 1);
    }

    public function test_cooking_needs_enough_clean_equipment(): void
    {
        $pot = $this->createOwnedEquipment('Pot', 1);
        $this->addRecipeEquipment($this->recipe, $pot, 1, EquipmentStage::COOKING);
        $meal = $this->plan();
        $this->prep($meal);
        $pot->amount_dirty = 1;
        $pot->save();

        $response = $this->cook($meal);

        $response->assertBadRequest();
        $this->assertEquals('Not enough clean Pot: 1 needed, 0 clean', $response->json('message'));
        $this->assertEquals(Meal::STATUS_PREPPED, $meal->fresh()->status);
    }

    public function test_cooking_containers_stay_in_use_until_the_meal_is_gone(): void
    {
        $pot = $this->createOwnedEquipment('Pot', 1);
        $this->addRecipeEquipment($this->recipe, $pot, 1, EquipmentStage::COOKING, true);
        $meal = $this->plan();
        $this->prep($meal);
        $this->cook($meal)->assertOk();

        $this->advance(20);
        $this->mealAction('serve', $meal)->assertOk();
        $this->assertUsage($pot, 1, 0);

        $this->mealAction('cancel', $meal)->assertOk();
        $this->assertUsage($pot, 0, 1);
    }

    //| cancelling

    public function test_cancelling_a_planned_meal_returns_equipment_clean(): void
    {
        $knife = $this->createOwnedEquipment('Knife', 1);
        $this->addRecipeEquipment($this->recipe, $knife, 1);

        $this->mealAction('cancel', $this->plan())->assertOk();

        $this->assertUsage($knife, 0, 0);
    }

    public function test_scrapping_a_prepped_meal_dirties_its_containers(): void
    {
        $bowl = $this->createOwnedEquipment('Bowl', 1);
        $this->addRecipeEquipment($this->recipe, $bowl, 1, EquipmentStage::PREP, true);
        $meal = $this->plan();
        $this->prep($meal);

        $this->mealAction('cancel', $meal)->assertOk();

        $this->assertUsage($bowl, 0, 1);
    }

    //| cleaning

    public function test_cleaning_is_queued_and_done_by_the_cleaning_staff(): void
    {
        $cup = $this->createOwnedEquipment('Cup', 4);
        $cup->amount_dirty = 3;
        $cup->save();

        $response = $this->callService('equipment.clean', ['amount' => 2], ['equipment_uuid' => $cup->uuid]);

        $response->assertOk();
        $this->assertEquals('08:00', $response->json('data.clock.time'));
        $this->assertUsage($cup, 0, 3);
        // Each piece is a job of its own, cleaned one after the other by the single cleaner
        $this->assertEquals([5, 10], array_column($response->json('data.stations.cleaning.jobs'), 'minutes_left'));

        $this->advance(10);
        $this->assertUsage($cup, 0, 1);
    }

    public function test_only_dirty_pieces_not_yet_queued_can_be_cleaned(): void
    {
        $cup = $this->createOwnedEquipment('Cup', 4);
        $cup->amount_dirty = 2;
        $cup->save();

        $response = $this->callService('equipment.clean', ['amount' => 3], ['equipment_uuid' => $cup->uuid]);
        $response->assertBadRequest();
        $this->assertEquals('Only 2 Cup left to clean', $response->json('message'));

        $this->callService('equipment.clean', ['amount' => 2], ['equipment_uuid' => $cup->uuid])->assertOk();
        $response = $this->callService('equipment.clean', ['amount' => 1], ['equipment_uuid' => $cup->uuid]);
        $response->assertBadRequest();
        $this->assertEquals('Only 0 Cup left to clean', $response->json('message'));
    }

    public function test_cleaning_everything(): void
    {
        $cup = $this->createOwnedEquipment('Cup', 4);
        $cup->amount_dirty = 2;
        $cup->save();
        $knife = $this->createOwnedEquipment('Knife', 2);
        $knife->amount_dirty = 1;
        $knife->save();

        $response = $this->callService('equipment.clean-all');

        $response->assertOk();
        $this->assertEquals('08:00', $response->json('data.clock.time'));
        $this->assertCount(3, $response->json('data.stations.cleaning.jobs'));

        $this->advance(15);
        $this->assertUsage($cup, 0, 0);
        $this->assertUsage($knife, 0, 0);
    }

    public function test_cleaning_everything_needs_something_left_to_clean(): void
    {
        $response = $this->callService('equipment.clean-all');

        $response->assertBadRequest();
        $this->assertEquals('Nothing left to clean', $response->json('message'));
    }
}
