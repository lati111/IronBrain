<?php

namespace Tests\Feature\Api\DnD\Restaurant\FloorApi;

use App\Models\DnD\Restaurant\Customer;
use App\Models\DnD\Restaurant\Restaurant;
use Tests\Feature\Api\AbstractApiFeatureTester;
use Tests\Interfaces\Operations\PostOperationInterface;
use Tests\Traits\DnD\RestaurantTestHelper;
use Tests\Traits\Operations\PostEditOperation;
use Tests\Traits\ValidationTests;

class EditCustomerTest extends AbstractApiFeatureTester implements PostOperationInterface
{
    use PostEditOperation, ValidationTests, RestaurantTestHelper;

    private Restaurant $restaurant;

    public function setUp(): void
    {
        parent::setUp();
        $this->restaurant = $this->createTestRestaurant($this->getAdminUser());
        $this->item = $this->createTestCustomer($this->restaurant, null, 'Bilbo');
    }

    protected function getRoute(?Customer $customer = null): string
    {
        return route('api.dnd.restaurant.floor.customer.edit', [
            'restaurant_uuid' => $this->restaurant->uuid,
            'customer_uuid'   => ($customer ?? $this->item)->uuid,
        ]);
    }

    protected function getModel(): Customer
    {
        return new Customer();
    }

    function getPostParameters(array $overwrites = []): array
    {
        return array_merge([
            'name'        => 'Bilbo Baggins',
            'description' => 'Back again',
        ], $overwrites);
    }

    //| validation tests

    public function test_name_validation(): void
    {
        $this->assertMaxValidation('string', 'name', 64);
    }

    //| save tests

    public function test_editing_keeps_the_customer_seated(): void
    {
        $table = $this->createTestTable($this->restaurant);
        $this->item->table_uuid = $table->uuid;
        $this->item->save();

        $this->getHttpClient($this->getOperationUser())->post($this->getRoute(), $this->getPostParameters())->assertOk();

        $this->assertEquals($table->uuid, $this->item->fresh()->table_uuid);
    }

    public function test_customer_of_another_restaurant_is_not_found(): void
    {
        $otherCustomer = $this->createTestCustomer($this->createTestRestaurant($this->getAdminUser(), 'Other'));

        $response = $this->getHttpClient($this->getOperationUser())->post($this->getRoute($otherCustomer), $this->getPostParameters());

        $response->assertNotFound();
        $this->assertApiMessage('Customer not found', $response);
    }
}
