<?php

namespace Tests\Feature\Api\DnD\Restaurant\RestaurantApi;

use App\Models\DnD\Restaurant\Restaurant;
use Tests\Feature\Api\AbstractApiFeatureTester;
use Tests\Interfaces\Operations\PostOperationInterface;
use Tests\Traits\DnD\RestaurantTestHelper;
use Tests\Traits\Operations\PostEditOperation;
use Tests\Traits\ValidationTests;

class EditRestaurantTest extends AbstractApiFeatureTester implements PostOperationInterface
{
    use PostEditOperation, ValidationTests, RestaurantTestHelper;

    public function setUp(): void
    {
        parent::setUp();
        // Refreshed, so the times are in the H:i:s format the database returns them in
        $this->item = $this->createTestRestaurant($this->getAdminUser())->fresh();
    }

    protected function getRoute(?Restaurant $restaurant = null): string
    {
        return route('api.dnd.restaurant.edit', ['restaurant_uuid' => ($restaurant ?? $this->item)->uuid]);
    }

    protected function getModel(): Restaurant
    {
        return new Restaurant();
    }

    function getPostParameters(array $overwrites = []): array
    {
        return array_merge([
            'name'   => 'The Green Dragon',
            'gold'   => 12,
            'silver' => 5,
            'copper' => 3,
            'bits'   => 40,
        ], $overwrites);
    }

    //| validation tests

    public function test_name_validation(): void
    {
        $this->assertMaxValidation('string', 'name', 64);
    }

    public function test_currency_validation(): void
    {
        $this->assertIntegerValidation('gold');
        $this->assertIntegerValidation('bits');
    }

    public function test_times_must_be_valid(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), ['opening_time' => '8 in the morning']);

        $response->assertBadRequest();
        $this->assertArrayHasKey('opening_time', $response->json('errors'));
    }

    //| save tests

    public function test_date_and_times_are_updated(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())->post($this->getRoute(), [
            'date'         => '1492-04-15',
            'opening_time' => '10:30',
            'closing_time' => '02:00',
        ]);

        $response->assertOk();
        $this->assertEquals('1492-04-15', $response->json('data.date'));
        $this->assertEquals('10:30', $response->json('data.opening_time'));
        $this->assertEquals('02:00', $response->json('data.closing_time'));
        $this->assertEquals('10:30:00', $this->item->fresh()->opening_time);
    }

    public function test_empty_name_keeps_the_current_name(): void
    {
        $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), ['name' => ''])
            ->assertOk();

        $this->assertEquals('Test Restaurant', $this->item->fresh()->name);
    }

    public function test_restaurant_without_access_is_not_found(): void
    {
        $restaurant = $this->createTestRestaurant($this->getRandomUser(), 'Someone else');

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute($restaurant), $this->getPostParameters());

        $response->assertNotFound();
        $this->assertApiMessage('Restaurant not found', $response);
        $this->assertEquals('Someone else', $restaurant->fresh()->name);
    }
}
