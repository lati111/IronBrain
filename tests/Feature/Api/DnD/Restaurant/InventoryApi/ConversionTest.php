<?php

namespace Tests\Feature\Api\DnD\Restaurant\InventoryApi;

use App\Enum\DnD\Restaurant\WorkStation;
use App\Models\DnD\Restaurant\Conversion;
use App\Models\DnD\Restaurant\Ingredient;
use App\Models\DnD\Restaurant\PileIngredient;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\Storage;
use App\Models\DnD\Restaurant\StorageIngredient;
use App\Models\DnD\Restaurant\Work;
use Illuminate\Testing\TestResponse;
use Tests\Feature\AbstractFeatureTester;
use Tests\Traits\DnD\RestaurantTestHelper;

/**
 * Covers conversions of an ingredient into another: managed in the inventory, converting stored stacks straight away
 * there, and done in the prep area during service. A potato becomes 4 fries, taking 2 minutes per potato
 */
class ConversionTest extends AbstractFeatureTester
{
    use RestaurantTestHelper;

    private Restaurant $restaurant;
    private Storage $cellar;
    private Ingredient $potato;
    private Ingredient $fries;
    private Conversion $conversion;

    public function setUp(): void
    {
        parent::setUp();
        $this->restaurant = $this->createTestRestaurant($this->getAdminUser());
        $this->restaurant->current_time = '08:00';
        $this->restaurant->save();

        $this->cellar = $this->createTestStorage($this->restaurant, 100, 0, 'Cellar');
        $this->potato = $this->createTestIngredient($this->restaurant, 1, 'Potato');
        $this->fries = $this->createTestIngredient($this->restaurant, 1, 'Fries');

        $this->conversion = new Conversion();
        $this->conversion->restaurant_uuid = $this->restaurant->uuid;
        $this->conversion->input_uuid = $this->potato->uuid;
        $this->conversion->output_uuid = $this->fries->uuid;
        $this->conversion->output_amount = 4;
        $this->conversion->minutes = 2;
        $this->conversion->save();
    }

    private function callRoute(string $route, array $parameters = [], array $routeParameters = []): TestResponse
    {
        return $this->withHeaders(['Accept' => 'application/json'])
            ->actingAs($this->getAdminUser())
            ->post(route($route, array_merge(['restaurant_uuid' => $this->restaurant->uuid], $routeParameters)), $parameters);
    }

    private function convert(array $parameters): TestResponse
    {
        return $this->callRoute('api.dnd.restaurant.inventory.conversion.convert', $parameters, ['conversion_uuid' => $this->conversion->uuid]);
    }

    private function queue(float $amount): TestResponse
    {
        return $this->callRoute('api.dnd.restaurant.service.conversion.queue', ['amount' => $amount], ['conversion_uuid' => $this->conversion->uuid]);
    }

    private function advance(int $minutes): TestResponse
    {
        return $this->callRoute('api.dnd.restaurant.service.time', ['advance' => $minutes])->assertOk();
    }

    private function getPileAmount(Ingredient $ingredient): float
    {
        return (float) PileIngredient::where('restaurant_uuid', $this->restaurant->uuid)->where('ingredient_uuid', $ingredient->uuid)->sum('amount');
    }

    //| managing

    public function test_conversions_are_created_and_listed(): void
    {
        $response = $this->callRoute('api.dnd.restaurant.inventory.conversion.create', [
            'input_uuid' => $this->fries->uuid, 'output_uuid' => $this->potato->uuid, 'output_amount' => 0.25, 'minutes' => 1.5,
        ]);

        $response->assertCreated();
        $this->assertEquals(
            ['input_uuid' => $this->fries->uuid, 'output_uuid' => $this->potato->uuid, 'output_amount' => 0.25, 'minutes' => 1.5],
            array_intersect_key(collect($response->json('data.inventory.conversions'))->firstWhere('uuid', $response->json('data.uuid')),
                array_flip(['input_uuid', 'output_uuid', 'output_amount', 'minutes'])));
    }

    public function test_conversions_are_validated(): void
    {
        $route = 'api.dnd.restaurant.inventory.conversion.create';
        $valid = ['input_uuid' => $this->potato->uuid, 'output_uuid' => $this->fries->uuid, 'output_amount' => 1, 'minutes' => 0];
        $other = $this->createTestIngredient($this->createTestRestaurant($this->getAdminUser(), 'Other'));

        $this->callRoute($route, array_merge($valid, ['output_uuid' => $this->potato->uuid]))->assertBadRequest();
        $this->callRoute($route, array_merge($valid, ['output_amount' => 0]))->assertBadRequest();
        $this->callRoute($route, array_merge($valid, ['minutes' => -1]))->assertBadRequest();
        $this->callRoute($route, array_merge($valid, ['output_uuid' => $other->uuid]))->assertNotFound();
    }

    public function test_conversions_are_edited_and_deleted(): void
    {
        $parameters = ['conversion_uuid' => $this->conversion->uuid];

        $this->callRoute('api.dnd.restaurant.inventory.conversion.edit', [
            'input_uuid' => $this->potato->uuid, 'output_uuid' => $this->fries->uuid, 'output_amount' => 6, 'minutes' => 3,
        ], $parameters)->assertOk();
        $this->assertEquals([6.0, 3.0], [$this->conversion->fresh()->output_amount, $this->conversion->fresh()->minutes]);

        $this->callRoute('api.dnd.restaurant.inventory.conversion.delete', [], $parameters)->assertOk();
        $this->assertNull($this->conversion->fresh());
    }

    //| in the inventory

    public function test_a_stored_stack_is_converted_where_it_is(): void
    {
        $old = $this->storeIngredient($this->cellar, $this->potato, 3, 2);

        $this->convert(['amount' => 2, 'from_storage_uuid' => $this->cellar->uuid, 'stack_uuid' => $old->uuid])->assertOk();

        $this->assertEquals(1, $this->getStoredIngredientAmount($this->cellar, $this->potato));
        $this->assertEquals(8, $this->getStoredIngredientAmount($this->cellar, $this->fries));
        // What it becomes is fresh
        $this->assertEquals(7, StorageIngredient::where('storage_uuid', $this->cellar->uuid)->where('ingredient_uuid', $this->fries->uuid)->value('days_left'));
    }

    public function test_the_pile_is_converted_in_the_pile(): void
    {
        $this->callRoute('api.dnd.restaurant.service.ingredient.add', ['amount' => 1], ['ingredient_uuid' => $this->potato->uuid])->assertOk();

        $this->convert(['amount' => 1, 'from_storage_uuid' => 'pile'])->assertOk();

        $this->assertEquals([0.0, 4.0], [$this->getPileAmount($this->potato), $this->getPileAmount($this->fries)]);
    }

    public function test_converting_needs_enough_input_and_room(): void
    {
        $this->storeIngredient($this->cellar, $this->potato, 1);
        $this->convert(['amount' => 2, 'from_storage_uuid' => $this->cellar->uuid])->assertBadRequest();

        // A single potato takes 1 room, while its 4 fries take 4
        $this->cellar->capacity = 3;
        $this->cellar->save();
        $response = $this->convert(['amount' => 1, 'from_storage_uuid' => $this->cellar->uuid]);

        $response->assertBadRequest();
        $this->assertEquals('Not enough capacity in Cellar', $response->json('message'));
        $this->assertEquals(1, $this->getStoredIngredientAmount($this->cellar, $this->potato));
        $this->assertEquals(0, $this->getStoredIngredientAmount($this->cellar, $this->fries));
    }

    //| during service

    public function test_a_conversion_is_prepped_and_ends_up_in_the_pile(): void
    {
        $this->storeIngredient($this->cellar, $this->potato, 5);
        $this->createTestEmployee($this->restaurant, WorkStation::PREP);

        $response = $this->queue(3)->assertOk();

        $this->assertEquals(2, $this->getStoredIngredientAmount($this->cellar, $this->potato));
        $job = collect($response->json('data.stations.prep.jobs'))->first();
        $this->assertEquals(['input_name' => 'Potato', 'input_amount' => 3, 'output_name' => 'Fries', 'output_amount' => 12], $job['conversion']);
        $this->assertEquals(6, $job['work']);

        $this->advance(5);
        $this->assertEquals(0, $this->getPileAmount($this->fries));

        $this->advance(1);
        $this->assertEquals(12, $this->getPileAmount($this->fries));
    }

    public function test_a_conversion_without_time_is_done_straight_away(): void
    {
        $this->conversion->minutes = 0;
        $this->conversion->save();
        $this->storeIngredient($this->cellar, $this->potato, 1);

        $this->queue(1)->assertOk();

        $this->assertEquals(4, $this->getPileAmount($this->fries));
        $this->assertFalse(Work::where('restaurant_uuid', $this->restaurant->uuid)->exists());
    }

    public function test_a_conversion_needs_enough_stored(): void
    {
        $this->storeIngredient($this->cellar, $this->potato, 1);

        $this->queue(2)->assertBadRequest();

        $this->assertFalse(Work::where('restaurant_uuid', $this->restaurant->uuid)->exists());
    }

    public function test_cancelling_a_conversion_puts_the_input_back(): void
    {
        $this->storeIngredient($this->cellar, $this->potato, 3, 2);
        $this->queue(2)->assertOk();
        $job = Work::where('restaurant_uuid', $this->restaurant->uuid)->first();

        $this->callRoute('api.dnd.restaurant.service.conversion.cancel', [], ['work_uuid' => $job->uuid])->assertOk();

        $this->assertNull($job->fresh());
        $this->assertEquals([[2.0, 3.0]], StorageIngredient::where('storage_uuid', $this->cellar->uuid)->where('ingredient_uuid', $this->potato->uuid)
            ->get()->map(fn (StorageIngredient $stack) => [$stack->days_left, $stack->amount])->all());
    }

    public function test_only_conversions_are_cancelled_this_way(): void
    {
        $meal = $this->createTestMeal($this->restaurant, $this->createTestRecipe($this->restaurant));
        $this->callRoute('api.dnd.restaurant.service.meal.prepare', [], ['meal_uuid' => $meal->uuid])->assertOk();
        $job = Work::where('meal_uuid', $meal->uuid)->first();

        $this->callRoute('api.dnd.restaurant.service.conversion.cancel', [], ['work_uuid' => $job->uuid])->assertBadRequest();
    }
}
