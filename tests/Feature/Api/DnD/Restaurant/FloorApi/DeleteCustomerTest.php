<?php

namespace Tests\Feature\Api\DnD\Restaurant\FloorApi;

use App\Models\DnD\Restaurant\Customer;
use App\Models\DnD\Restaurant\Restaurant;
use Tests\Feature\Api\AbstractApiFeatureTester;
use Tests\Traits\DnD\RestaurantTestHelper;

class DeleteCustomerTest extends AbstractApiFeatureTester
{
    use RestaurantTestHelper;

    private Restaurant $restaurant;
    private Customer $customer;

    public function setUp(): void
    {
        parent::setUp();
        $this->restaurant = $this->createTestRestaurant($this->getAdminUser());
        $this->customer = $this->createTestCustomer($this->restaurant);
    }

    protected function getOperationType(): string
    {
        return 'POST';
    }

    protected function getRoute(?Customer $customer = null): string
    {
        return route('api.dnd.restaurant.floor.customer.delete', [
            'restaurant_uuid' => $this->restaurant->uuid,
            'customer_uuid'   => ($customer ?? $this->customer)->uuid,
        ]);
    }

    protected function getModel(): Customer
    {
        return new Customer();
    }

    public function test_customer_is_deleted(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())->post($this->getRoute());

        $response->assertOk();
        $this->assertFalse(Customer::where('uuid', $this->customer->uuid)->exists());
        $this->assertEquals([], $response->json('data.floor.customers'));
    }

    public function test_customer_of_another_restaurant_is_not_found(): void
    {
        $otherCustomer = $this->createTestCustomer($this->createTestRestaurant($this->getAdminUser(), 'Other'));

        $response = $this->getHttpClient($this->getOperationUser())->post($this->getRoute($otherCustomer));

        $response->assertNotFound();
        $this->assertApiMessage('Customer not found', $response);
        $this->assertTrue(Customer::where('uuid', $otherCustomer->uuid)->exists());
    }
}
