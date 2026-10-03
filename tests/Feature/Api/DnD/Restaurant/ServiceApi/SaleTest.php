<?php

namespace Tests\Feature\Api\DnD\Restaurant\ServiceApi;

use App\Models\DnD\Restaurant\Recipe;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\Table;
use App\Service\DnD\Restaurant\ShiftService;
use Illuminate\Testing\TestResponse;
use Tests\Feature\AbstractFeatureTester;
use Tests\Traits\DnD\RestaurantTestHelper;

/**
 * Covers recipes put on sale for a single service. The test recipe usually costs 5
 */
class SaleTest extends AbstractFeatureTester
{
    use RestaurantTestHelper;

    private Restaurant $restaurant;
    private Recipe $recipe;
    private Table $table;

    public function setUp(): void
    {
        parent::setUp();
        $this->restaurant = $this->createTestRestaurant($this->getAdminUser());
        $this->recipe = $this->createTestRecipe($this->restaurant, 'Stew');
        $this->table = $this->createTestTable($this->restaurant, null, 4, 'Booth');

        app(ShiftService::class)->startService($this->restaurant, '09:00');
    }

    private function callService(string $route, array $parameters = [], array $routeParameters = []): TestResponse
    {
        return $this->withHeaders(['Accept' => 'application/json'])
            ->actingAs($this->getAdminUser())
            ->postJson(route('api.dnd.restaurant.service.' . $route, array_merge(['restaurant_uuid' => $this->restaurant->uuid], $routeParameters)), $parameters);
    }

    private function setSale(float|string $price): TestResponse
    {
        return $this->callService('sale.set', ['price' => $price], ['recipe_uuid' => $this->recipe->uuid]);
    }

    private function order(): TestResponse
    {
        return $this->callService('order.place', [
            'table_uuid' => $this->table->uuid,
            'items' => [['recipe_uuid' => $this->recipe->uuid, 'customer_uuid' => null]],
        ]);
    }

    private function getRecipeState(TestResponse $response): array
    {
        return collect($response->json('data.recipes'))->firstWhere('name', 'Stew');
    }

    public function test_recipes_on_sale_are_ordered_at_the_sale_price(): void
    {
        $response = $this->setSale(3.5);

        $response->assertOk();
        $this->assertEquals(['price' => 3.5, 'base_price' => 5, 'on_sale' => true],
            array_intersect_key($this->getRecipeState($response), array_flip(['price', 'base_price', 'on_sale'])));

        $response = $this->order();

        $response->assertCreated();
        $this->assertEquals(3.5, $response->json('data.orders.0.total_price'));
        $this->assertEquals(3.5, $response->json('data.clock.shift_total'));
        // The recipe itself keeps its usual price
        $this->assertEquals(5.0, $this->recipe->fresh()->price);
    }

    public function test_a_sale_can_be_changed_and_removed(): void
    {
        $this->setSale(3)->assertOk();
        $this->assertEquals(4.0, $this->getRecipeState($this->setSale(4))['price']);

        $response = $this->callService('sale.remove', [], ['recipe_uuid' => $this->recipe->uuid]);

        $response->assertOk();
        $this->assertFalse($this->getRecipeState($response)['on_sale']);
        $this->assertEquals(5.0, $this->order()->json('data.orders.0.total_price'));
    }

    public function test_sales_last_for_one_service_only(): void
    {
        $this->setSale(1)->assertOk();
        $shifts = app(ShiftService::class);
        $shifts->endService($this->restaurant->fresh());
        $shifts->startService($this->restaurant->fresh(), '09:00');

        $response = $this->order();

        $this->assertEquals(5.0, $response->json('data.orders.0.total_price'));
    }

    public function test_a_sale_needs_an_ongoing_service(): void
    {
        app(ShiftService::class)->endService($this->restaurant->fresh());

        $response = $this->setSale(1);

        $response->assertBadRequest();
        $this->assertEquals('No service is ongoing', $response->json('message'));
    }

    public function test_sale_prices_cannot_be_negative(): void
    {
        $this->setSale(-1)->assertBadRequest();
        $this->setSale('free')->assertBadRequest();
    }

    public function test_recipe_of_another_restaurant_is_not_found(): void
    {
        $other = $this->createTestRecipe($this->createTestRestaurant($this->getAdminUser(), 'Other'));

        $this->callService('sale.set', ['price' => 1], ['recipe_uuid' => $other->uuid])->assertNotFound();
    }
}
