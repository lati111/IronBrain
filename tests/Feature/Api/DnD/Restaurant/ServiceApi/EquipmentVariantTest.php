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
 * Covers using a variant of a piece of equipment instead of the one the recipe takes, when planning a meal for its
 * prep equipment and when putting it in a cooker for its cooking equipment. The test recipe takes 10 minutes to prep
 */
class EquipmentVariantTest extends AbstractFeatureTester
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
    }

    /** Creates equipment that doesn't need storage, with a number of pieces owned, optionally as a variant of another */
    private function createOwnedEquipment(string $name, int $pieces, ?Equipment $parent = null): Equipment
    {
        $equipment = $this->createTestEquipment($this->restaurant, 1, $name, false);
        $equipment->parent_uuid = $parent?->uuid;
        $equipment->save();
        $this->ownEquipment($this->restaurant, $equipment, $pieces);
        return $equipment;
    }

    private function callService(string $route, array $parameters = [], array $routeParameters = []): TestResponse
    {
        return $this->withHeaders(['Accept' => 'application/json'])
            ->actingAs($this->getAdminUser())
            ->post(route('api.dnd.restaurant.service.' . $route, array_merge(['restaurant_uuid' => $this->restaurant->uuid], $routeParameters)), $parameters);
    }

    private function plan(array $equipment = []): TestResponse
    {
        return $this->callService('meal.plan', ['recipe_uuid' => $this->recipe->uuid, 'equipment' => $equipment]);
    }

    private function latestMeal(): Meal
    {
        return Meal::where('restaurant_uuid', $this->restaurant->uuid)->latest()->first();
    }

    private function cook(Meal $meal, array $equipment = []): TestResponse
    {
        return $this->callService('meal.cook', ['cooker_uuid' => $this->stove->uuid, 'slot' => 1, 'equipment' => $equipment], ['meal_uuid' => $meal->uuid]);
    }

    private function prep(Meal $meal): void
    {
        $this->callService('meal.prepare', [], ['meal_uuid' => $meal->uuid])->assertOk();
        $this->callService('time', ['advance' => 10])->assertOk();
    }

    private function assertInUse(Equipment $equipment, int $inUse): void
    {
        $this->assertEquals($inUse, $equipment->refresh()->amount_in_use, $equipment->name . ' in use');
    }

    public function test_the_state_lists_variants_with_how_many_are_clean(): void
    {
        $knife = $this->createOwnedEquipment('Knife', 2);
        $cleaver = $this->createOwnedEquipment('Cleaver', 3, $knife);
        $cleaver->amount_dirty = 1;
        $cleaver->save();
        $this->addRecipeEquipment($this->recipe, $knife, 1);

        $response = $this->withHeaders(['Accept' => 'application/json'])->actingAs($this->getAdminUser())
            ->get(route('api.dnd.restaurant.service.get', ['restaurant_uuid' => $this->restaurant->uuid]));

        $entry = collect($response->json('data.recipes'))->firstWhere('name', 'Stew')['equipment'][0];
        $this->assertEquals($knife->uuid, $entry['equipment_uuid']);
        $this->assertEquals('prep', $entry['stage']);
        $this->assertEquals([
            ['uuid' => $knife->uuid, 'name' => 'Knife', 'clean' => 2],
            ['uuid' => $cleaver->uuid, 'name' => 'Cleaver', 'clean' => 2],
        ], $entry['options']);
    }

    public function test_planning_uses_the_equipment_itself_by_default(): void
    {
        $knife = $this->createOwnedEquipment('Knife', 2);
        $cleaver = $this->createOwnedEquipment('Cleaver', 2, $knife);
        $this->addRecipeEquipment($this->recipe, $knife, 2);

        $this->plan()->assertCreated();

        $this->assertInUse($knife, 2);
        $this->assertInUse($cleaver, 0);
    }

    public function test_planning_can_use_a_variant_of_the_prep_equipment(): void
    {
        $knife = $this->createOwnedEquipment('Knife', 0);
        $cleaver = $this->createOwnedEquipment('Cleaver', 2, $knife);
        $this->addRecipeEquipment($this->recipe, $knife, 2);

        $this->plan([$knife->uuid => $cleaver->uuid])->assertCreated();

        $this->assertInUse($cleaver, 2);
        $this->assertEquals($cleaver->uuid, $this->latestMeal()->equipment[0]['equipment_uuid']);

        // The variant is what becomes dirty once prepped
        $this->prep($this->latestMeal());
        $this->assertEquals(2, $cleaver->refresh()->amount_dirty);
        $this->assertEquals(0, $knife->refresh()->amount_dirty);
    }

    public function test_a_variant_needs_enough_clean(): void
    {
        $knife = $this->createOwnedEquipment('Knife', 2);
        $cleaver = $this->createOwnedEquipment('Cleaver', 1, $knife);
        $this->addRecipeEquipment($this->recipe, $knife, 2);

        $response = $this->plan([$knife->uuid => $cleaver->uuid]);

        $response->assertBadRequest();
        $this->assertEquals('Not enough clean Cleaver: 2 needed, 1 clean', $response->json('message'));
        $this->assertInUse($knife, 0);
    }

    public function test_only_variants_can_be_used_instead(): void
    {
        $knife = $this->createOwnedEquipment('Knife', 2);
        $spoon = $this->createOwnedEquipment('Spoon', 2);
        $this->addRecipeEquipment($this->recipe, $knife, 1);

        $response = $this->plan([$knife->uuid => $spoon->uuid]);

        $response->assertBadRequest();
        $this->assertEquals('That equipment cannot be used instead', $response->json('message'));
        $this->assertFalse(Meal::where('restaurant_uuid', $this->restaurant->uuid)->exists());
    }

    public function test_cooking_can_use_a_variant_of_the_cooking_equipment(): void
    {
        $pot = $this->createOwnedEquipment('Pot', 1);
        $cauldron = $this->createOwnedEquipment('Cauldron', 1, $pot);
        $this->addRecipeEquipment($this->recipe, $pot, 1, EquipmentStage::COOKING);

        $this->plan([$pot->uuid => $cauldron->uuid])->assertCreated();
        $meal = $this->latestMeal();
        // A prep choice doesn't touch the cooking equipment
        $this->assertEquals($pot->uuid, $meal->equipment[0]['equipment_uuid']);
        $this->prep($meal);

        $this->cook($meal->refresh(), [$pot->uuid => $cauldron->uuid])->assertOk();

        $this->assertInUse($cauldron, 1);
        $this->assertInUse($pot, 0);
        $this->assertEquals($cauldron->uuid, $meal->refresh()->equipment[0]['equipment_uuid']);
    }
}
