<?php

namespace Tests\Feature\Api\DnD\Restaurant\RestaurantApi;

use App\Models\DnD\Restaurant\Restaurant;
use Tests\Feature\Api\AbstractApiFeatureTester;
use Tests\Interfaces\Operations\PostOperationInterface;
use Tests\Traits\Operations\PostSaveOperation;
use Tests\Traits\ValidationTests;

class CreateRestaurantTest extends AbstractApiFeatureTester implements PostOperationInterface
{
    use PostSaveOperation, ValidationTests;

    protected function getRoute(): string
    {
        return route('api.dnd.restaurant.create');
    }

    protected function getModel(): Restaurant
    {
        return new Restaurant();
    }

    function getPostParameters(array $overwrites = []): array
    {
        return array_merge([
            'name' => 'The Prancing Pony',
        ], $overwrites);
    }

    //| validation tests

    public function test_name_validation(): void
    {
        $this->assertRequiredValidation('name');
        $this->assertStringValidation('name');
        $this->assertMaxValidation('string', 'name', 64);
    }

    //| restaurant is saved test

    public function test_restaurant_is_saved_for_user(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), $this->getPostParameters());

        $response->assertCreated();
        $this->assertApiMessage('Restaurant created', $response);

        $restaurant = Restaurant::where('uuid', $response->json('data'))->first();
        $this->assertNotNull($restaurant);
        $this->assertEquals('The Prancing Pony', $restaurant->name);
        $this->assertEquals($this->getAdminUser()->uuid, $restaurant->owner_uuid);
        $this->assertEquals(0, $restaurant->fresh()->gold);
    }

    //| permission test

    public function test_forbidden_without_permission(): void
    {
        $count = Restaurant::count();

        $response = $this->withHeaders($this->getDefaultHeaders())
            ->actingAs($this->getRandomUser())
            ->post($this->getRoute(), $this->getPostParameters());

        $response->assertViewIs('errors.auth.forbidden');
        $this->assertEquals($count, Restaurant::count());
    }
}
