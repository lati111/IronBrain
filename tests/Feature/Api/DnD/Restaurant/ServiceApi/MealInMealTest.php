<?php

namespace Tests\Feature\Api\DnD\Restaurant\ServiceApi;

use App\Enum\DnD\Restaurant\EquipmentStage;
use App\Models\DnD\Restaurant\Equipment;
use App\Models\DnD\Restaurant\Meal;
use App\Models\DnD\Restaurant\Recipe;
use App\Models\DnD\Restaurant\RecipeMeal;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\ServedMeal;
use App\Models\DnD\Restaurant\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Feature\AbstractFeatureTester;
use Tests\Traits\DnD\RestaurantTestHelper;

/**
 * Covers recipes taking other meals: set on the recipe, either as a specific recipe or a category of recipe, and put in
 * during service by dropping a finished meal on the meal being prepared. The roast takes 2 portions of gravy
 */
class MealInMealTest extends AbstractFeatureTester
{
    use RestaurantTestHelper;

    private Restaurant $restaurant;
    private Storage $cellar;
    private Recipe $gravy;
    private Recipe $roast;

    public function setUp(): void
    {
        parent::setUp();
        $this->restaurant = $this->createTestRestaurant($this->getAdminUser());
        $this->restaurant->current_time = '08:00';
        $this->restaurant->save();

        $this->cellar = $this->createTestStorage($this->restaurant, 100, 0, 'Cellar');
        $this->gravy = $this->createTestRecipe($this->restaurant, 'Gravy');
        $this->gravy->category = 'Sauce';
        $this->gravy->save();
        $this->roast = $this->createTestRecipe($this->restaurant, 'Roast');
        $this->requireMeal($this->roast, $this->gravy, null, 2);
    }

    private function requireMeal(Recipe $recipe, ?Recipe $required, ?string $category, int $portions): RecipeMeal
    {
        $requirement = new RecipeMeal();
        $requirement->recipe_uuid = $recipe->uuid;
        $requirement->required_recipe_uuid = $required?->uuid;
        $requirement->category = $category;
        $requirement->portions = $portions;
        $requirement->save();

        return $requirement;
    }

    /** A meal ready in the serving area, holding the given equipment, which is put in use */
    private function createFinishedMeal(Recipe $recipe, int $portions, array $equipment = []): Meal
    {
        $meal = $this->createTestMeal($this->restaurant, $recipe, Meal::STATUS_SERVING);
        $meal->portions_left = $portions;
        $meal->expires_at = $this->restaurant->getNow()->addHour();
        $meal->equipment = $equipment;
        $meal->save();

        foreach ($equipment as $entry) {
            $piece = Equipment::find($entry['equipment_uuid']);
            $piece->amount_in_use += $entry['amount'];
            $piece->save();
        }

        return $meal;
    }

    private function createRoastMeal(string $status = Meal::STATUS_PLANNED): Meal
    {
        $meal = $this->createTestMeal($this->restaurant, $this->roast, $status);
        $meal->portions_left = 1;
        $meal->equipment = [];
        $meal->save();

        return $meal;
    }

    private function callRoute(string $route, array $parameters = [], array $routeParameters = []): TestResponse
    {
        return $this->withHeaders(['Accept' => 'application/json'])
            ->actingAs($this->getAdminUser())
            ->post(route($route, array_merge(['restaurant_uuid' => $this->restaurant->uuid], $routeParameters)), $parameters);
    }

    private function supply(Meal $meal, Meal $source, bool $plateRemaining = false): TestResponse
    {
        return $this->callRoute('api.dnd.restaurant.service.meal.supply',
            ['source_uuid' => $source->uuid, 'plate_remaining' => $plateRemaining ? 1 : 0], ['meal_uuid' => $meal->uuid]);
    }

    private function cook(Meal $meal, Equipment $cooker): TestResponse
    {
        return $this->callRoute('api.dnd.restaurant.service.meal.cook', ['cooker_uuid' => $cooker->uuid, 'slot' => 1], ['meal_uuid' => $meal->uuid]);
    }

    private function createPot(int $stored): Equipment
    {
        $pot = $this->createTestEquipment($this->restaurant, 1, 'Pot');
        $this->storeEquipment($this->cellar, $pot, $stored);

        return $pot;
    }

    private function potEntry(Equipment $pot, EquipmentStage $stage = EquipmentStage::COOKING): array
    {
        return ['equipment_uuid' => $pot->uuid, 'amount' => 1, 'stage' => $stage->value, 'container' => true];
    }

    //| recipes

    public function test_recipes_are_created_with_a_type_category_and_meals(): void
    {
        $response = $this->callRoute('api.dnd.restaurant.inventory.recipe.create', [
            'name' => 'Pie', 'type' => 'Dessert', 'category' => 'Baked', 'price' => 4, 'portions' => 4, 'difficulty' => 1,
            'prep_time' => 10, 'cooking_time' => 30, 'fresh_for' => 60, 'good_for' => 8, 'expires_in' => 3,
            'meals' => [['recipe_uuid' => $this->gravy->uuid, 'portions' => 1], ['category' => 'Sauce', 'portions' => 2]],
        ]);

        $response->assertCreated();
        $pie = collect($response->json('data.inventory.recipes'))->firstWhere('name', 'Pie');
        $this->assertEquals('Dessert', $pie['type']);
        $this->assertEquals('Baked', $pie['category']);
        $this->assertEquals([
            ['recipe_uuid' => $this->gravy->uuid, 'category' => null, 'portions' => 1],
            ['recipe_uuid' => null, 'category' => 'Sauce', 'portions' => 2],
        ], $pie['meals']);
    }

    public function test_recipes_are_meals_without_a_category_by_default(): void
    {
        $response = $this->callRoute('api.dnd.restaurant.inventory.recipe.create', [
            'name' => 'Soup', 'type' => '', 'price' => 4, 'difficulty' => 1, 'prep_time' => 10, 'cooking_time' => 30,
            'fresh_for' => 60, 'good_for' => 8, 'expires_in' => 3,
        ]);

        $response->assertCreated();
        $soup = Recipe::where('uuid', $response->json('data.uuid'))->first();
        $this->assertEquals('meal', $soup->type);
        $this->assertNull($soup->category);
    }

    public function test_editing_keeps_the_meals_unless_given(): void
    {
        $route = 'api.dnd.restaurant.inventory.recipe.edit';
        $parameters = ['recipe_uuid' => $this->roast->uuid];

        $this->callRoute($route, ['name' => 'Sunday Roast', 'category' => 'Main'], $parameters)->assertOk();
        $this->assertEquals('Main', $this->roast->fresh()->category);
        $this->assertEquals('meal', $this->roast->fresh()->type);
        $this->assertEquals(1, RecipeMeal::where('recipe_uuid', $this->roast->uuid)->count());

        $this->callRoute($route, ['meals' => [['category' => 'Sauce', 'portions' => 1]]], $parameters)->assertOk();
        $this->assertEquals([[null, 'Sauce']], RecipeMeal::where('recipe_uuid', $this->roast->uuid)->get()
            ->map(fn (RecipeMeal $entry) => [$entry->required_recipe_uuid, $entry->category])->all());
    }

    public function test_a_recipe_cant_require_itself_or_another_restaurants_recipe(): void
    {
        $route = 'api.dnd.restaurant.inventory.recipe.edit';
        $parameters = ['recipe_uuid' => $this->roast->uuid];
        $other = $this->createTestRecipe($this->createTestRestaurant($this->getAdminUser(), 'Other'));

        $this->callRoute($route, ['meals' => [['recipe_uuid' => $this->roast->uuid, 'portions' => 1]]], $parameters)->assertBadRequest();
        $this->callRoute($route, ['meals' => [['recipe_uuid' => $other->uuid, 'portions' => 1]]], $parameters)->assertNotFound();
        $this->callRoute($route, ['meals' => [['portions' => 1]]], $parameters)->assertBadRequest();
        $this->callRoute($route, ['meals' => [['category' => 'Sauce', 'portions' => 0]]], $parameters)->assertBadRequest();
    }

    //| putting meals in

    public function test_the_kitchen_lists_the_meals_still_missing(): void
    {
        $this->createRoastMeal();

        $response = $this->withHeaders(['Accept' => 'application/json'])->actingAs($this->getAdminUser())
            ->get(route('api.dnd.restaurant.service.get', ['restaurant_uuid' => $this->restaurant->uuid]));

        $this->assertEquals([['recipe_uuid' => $this->gravy->uuid, 'category' => null, 'name' => 'Gravy', 'portions' => 2]],
            collect($response->json('data.meals'))->firstWhere('name', 'Roast')['missing_meals']);
    }

    public function test_a_finished_meal_is_put_in(): void
    {
        $roast = $this->createRoastMeal();
        $gravy = $this->createFinishedMeal($this->gravy, 2);

        $response = $this->supply($roast, $gravy);

        $response->assertOk();
        $this->assertNull($gravy->fresh());
        $this->assertEquals([], collect($response->json('data.meals'))->firstWhere('name', 'Roast')['missing_meals']);
    }

    public function test_a_meal_of_the_category_is_put_in(): void
    {
        $stew = $this->createTestRecipe($this->restaurant, 'Stew');
        $this->requireMeal($stew, null, 'Sauce', 1);
        $meal = $this->createTestMeal($this->restaurant, $stew);
        $meal->portions_left = 1;
        $meal->save();

        $this->supply($meal, $this->createFinishedMeal($this->gravy, 1))->assertOk();

        $this->assertNotEmpty($meal->fresh()->supplied);
    }

    public function test_leftover_portions_are_only_plated_when_allowed(): void
    {
        $roast = $this->createRoastMeal();
        $gravy = $this->createFinishedMeal($this->gravy, 5);

        $response = $this->supply($roast, $gravy);
        $response->assertBadRequest();
        $this->assertEquals('The 3 portions of Gravy left over need to be plated', $response->json('message'));
        $this->assertEquals(5, $gravy->fresh()->portions_left);

        $this->supply($roast, $gravy, plateRemaining: true)->assertOk();

        $this->assertNull($gravy->fresh());
        $this->assertEquals(3, ServedMeal::where('restaurant_uuid', $this->restaurant->uuid)->where('recipe_uuid', $this->gravy->uuid)->count());
    }

    public function test_the_meal_put_in_needs_enough_portions(): void
    {
        $roast = $this->createRoastMeal();
        $gravy = $this->createFinishedMeal($this->gravy, 1);

        $response = $this->supply($roast, $gravy);

        $response->assertBadRequest();
        $this->assertEquals('Not enough Gravy: 2 portions needed, 1 left', $response->json('message'));
        $this->assertNotNull($gravy->fresh());
    }

    public function test_only_a_needed_meal_is_put_in(): void
    {
        $roast = $this->createRoastMeal();
        $soup = $this->createFinishedMeal($this->createTestRecipe($this->restaurant, 'Soup'), 2);

        $response = $this->supply($roast, $soup);

        $response->assertBadRequest();
        $this->assertEquals('Roast doesn\'t need Soup', $response->json('message'));
    }

    public function test_only_fresh_finished_meals_are_put_in(): void
    {
        $roast = $this->createRoastMeal();

        $prepped = $this->createTestMeal($this->restaurant, $this->gravy, Meal::STATUS_PREPPED);
        $prepped->portions_left = 2;
        $prepped->save();
        $this->supply($roast, $prepped)->assertBadRequest();

        $spoiled = $this->createFinishedMeal($this->gravy, 2);
        $spoiled->expires_at = $this->restaurant->getNow()->subMinute();
        $spoiled->save();
        $this->supply($roast, $spoiled)->assertBadRequest();

        $this->assertEmpty($roast->fresh()->supplied);
    }

    public function test_a_meal_of_another_restaurant_is_not_found(): void
    {
        $other = $this->createTestRestaurant($this->getAdminUser(), 'Other');
        $foreign = $this->createTestMeal($other, $this->createTestRecipe($other, 'Gravy'), Meal::STATUS_SERVING);

        $this->supply($this->createRoastMeal(), $foreign)->assertNotFound();
    }

    //| cooking

    public function test_a_meal_is_only_cooked_once_everything_is_put_in(): void
    {
        $cooker = $this->createTestCooker($this->restaurant);
        $roast = $this->createRoastMeal(Meal::STATUS_PREPPED);

        $response = $this->cook($roast, $cooker);
        $response->assertBadRequest();
        $this->assertEquals('Roast still needs Gravy', $response->json('message'));

        $this->supply($roast, $this->createFinishedMeal($this->gravy, 2))->assertOk();
        $this->cook($roast, $cooker)->assertOk();
    }

    public function test_a_meal_without_cooking_is_ready_once_everything_is_put_in(): void
    {
        $this->roast->cooking_time = 0;
        $this->roast->save();
        $roast = $this->createRoastMeal(Meal::STATUS_PREPPED);

        $this->supply($roast, $this->createFinishedMeal($this->gravy, 2))->assertOk();

        $this->assertEquals(Meal::STATUS_SERVING, $roast->fresh()->status);
    }

    //| equipment

    public function test_shared_equipment_stays_in_use_for_cooking(): void
    {
        // The only pot holds the gravy, and the roast cooks in a pot as well
        $pot = $this->createPot(1);
        $this->addRecipeEquipment($this->roast, $pot, 1, EquipmentStage::COOKING, true);
        $roast = $this->createRoastMeal(Meal::STATUS_PREPPED);
        $roast->equipment = [$this->potEntry($pot)];
        $roast->save();

        $this->supply($roast, $this->createFinishedMeal($this->gravy, 2, [$this->potEntry($pot)]))->assertOk();

        $this->assertEquals([1, 0], [$pot->fresh()->amount_in_use, $pot->fresh()->amount_dirty]);
        $this->assertEquals([$pot->uuid => 1], $roast->fresh()->carried_equipment);

        $this->cook($roast, $this->createTestCooker($this->restaurant))->assertOk();

        $this->assertEquals([1, 0], [$pot->fresh()->amount_in_use, $pot->fresh()->amount_dirty]);
        $this->assertNull($roast->fresh()->carried_equipment);
    }

    public function test_shared_equipment_stands_in_for_what_the_meal_already_holds(): void
    {
        // The roast holds a pot for prepping, which goes back clean as the gravy's pot takes its place
        $pot = $this->createPot(2);
        $roast = $this->createRoastMeal();
        $roast->equipment = [$this->potEntry($pot, EquipmentStage::PREP)];
        $roast->save();
        $pot->amount_in_use = 1;
        $pot->save();

        $this->supply($roast, $this->createFinishedMeal($this->gravy, 2, [$this->potEntry($pot)]))->assertOk();

        $this->assertEquals([1, 0], [$pot->fresh()->amount_in_use, $pot->fresh()->amount_dirty]);
        $this->assertNull($roast->fresh()->carried_equipment);
    }

    public function test_equipment_the_meal_doesnt_use_is_washed(): void
    {
        $pot = $this->createPot(1);
        $roast = $this->createRoastMeal();

        $this->supply($roast, $this->createFinishedMeal($this->gravy, 2, [$this->potEntry($pot)]))->assertOk();

        $this->assertEquals([0, 1], [$pot->fresh()->amount_in_use, $pot->fresh()->amount_dirty]);
    }

    public function test_cancelling_washes_the_carried_equipment(): void
    {
        $pot = $this->createPot(1);
        $this->addRecipeEquipment($this->roast, $pot, 1, EquipmentStage::COOKING, true);
        $roast = $this->createRoastMeal();
        $roast->equipment = [$this->potEntry($pot)];
        $roast->save();
        $this->supply($roast, $this->createFinishedMeal($this->gravy, 2, [$this->potEntry($pot)]))->assertOk();

        $this->callRoute('api.dnd.restaurant.service.meal.cancel', [], ['meal_uuid' => $roast->uuid])->assertOk();

        $this->assertEquals([0, 1], [$pot->fresh()->amount_in_use, $pot->fresh()->amount_dirty]);
    }
}
