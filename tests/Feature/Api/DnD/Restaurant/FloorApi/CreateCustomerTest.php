<?php

namespace Tests\Feature\Api\DnD\Restaurant\FloorApi;

use App\Models\DnD\Restaurant\Customer;
use App\Models\DnD\Restaurant\Restaurant;
use Tests\Feature\Api\AbstractApiFeatureTester;
use Tests\Interfaces\Operations\PostOperationInterface;
use Tests\Traits\DnD\RestaurantTestHelper;
use Tests\Traits\Operations\PostSaveOperation;
use Tests\Traits\ValidationTests;

class CreateCustomerTest extends AbstractApiFeatureTester implements PostOperationInterface
{
    use PostSaveOperation, ValidationTests, RestaurantTestHelper;

    private Restaurant $restaurant;

    public function setUp(): void
    {
        parent::setUp();
        $this->restaurant = $this->createTestRestaurant($this->getAdminUser());
    }

    protected function getRoute(): string
    {
        return route('api.dnd.restaurant.floor.customer.create', ['restaurant_uuid' => $this->restaurant->uuid]);
    }

    protected function getModel(): Customer
    {
        return new Customer();
    }

    function getPostParameters(array $overwrites = []): array
    {
        return array_merge([
            'name'        => 'Bilbo Baggins',
            'description' => 'Fond of second breakfast',
        ], $overwrites);
    }

    //| validation tests

    public function test_name_validation(): void
    {
        $this->assertRequiredValidation('name');
        $this->assertMaxValidation('string', 'name', 64);
    }

    //| save tests

    public function test_customer_is_created_unseated(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())->post($this->getRoute(), $this->getPostParameters());

        $response->assertCreated();
        $customer = Customer::where('uuid', $response->json('data.uuid'))->first();
        $this->assertEquals($this->restaurant->uuid, $customer->restaurant_uuid);
        $this->assertEquals('Fond of second breakfast', $customer->description);
        $this->assertNull($customer->table_uuid);
    }

    public function test_seating_is_not_set_on_creation(): void
    {
        $table = $this->createTestTable($this->restaurant);

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), $this->getPostParameters(['table_uuid' => $table->uuid]));

        $response->assertCreated();
        $this->assertNull(Customer::where('uuid', $response->json('data.uuid'))->value('table_uuid'));
    }

    public function test_restaurant_without_access_is_not_found(): void
    {
        $restaurant = $this->createTestRestaurant($this->getRandomUser(), 'Someone else');

        $response = $this->getHttpClient($this->getOperationUser())->post(
            route('api.dnd.restaurant.floor.customer.create', ['restaurant_uuid' => $restaurant->uuid]),
            $this->getPostParameters(),
        );

        $response->assertNotFound();
        $this->assertApiMessage('Restaurant not found', $response);
    }
}
