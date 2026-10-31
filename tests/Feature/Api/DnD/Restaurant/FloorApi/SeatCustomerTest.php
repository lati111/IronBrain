<?php

namespace Tests\Feature\Api\DnD\Restaurant\FloorApi;

use App\Models\DnD\Restaurant\Customer;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\Table;
use Tests\Feature\Api\AbstractApiFeatureTester;
use Tests\Interfaces\Operations\PostOperationInterface;
use Tests\Traits\DnD\RestaurantTestHelper;
use Tests\Traits\Operations\PostOperation;
use Tests\Traits\ValidationTests;

class SeatCustomerTest extends AbstractApiFeatureTester implements PostOperationInterface
{
    use PostOperation, ValidationTests, RestaurantTestHelper;

    private Restaurant $restaurant;
    private Table $table;
    private Customer $customer;

    public function setUp(): void
    {
        parent::setUp();
        $this->restaurant = $this->createTestRestaurant($this->getAdminUser());
        $this->table = $this->createTestTable($this->restaurant, null, 2, 'Booth');
        $this->customer = $this->createTestCustomer($this->restaurant, null, 'Bilbo');
    }

    protected function getRoute(?Customer $customer = null): string
    {
        return route('api.dnd.restaurant.floor.customer.seat', [
            'restaurant_uuid' => $this->restaurant->uuid,
            'customer_uuid'   => ($customer ?? $this->customer)->uuid,
        ]);
    }

    protected function getModel(): Customer
    {
        return new Customer();
    }

    function getPostParameters(array $overwrites = []): array
    {
        return array_merge([
            'table_uuid' => $this->table->uuid,
            'seat'       => 2,
        ], $overwrites);
    }

    private function seat(array $parameters, ?Customer $customer = null)
    {
        return $this->getHttpClient($this->getOperationUser())->post($this->getRoute($customer), $parameters);
    }

    //| validation tests

    public function test_seat_validation(): void
    {
        $this->assertIntegerValidation('seat');
    }

    public function test_seat_is_required_with_a_table(): void
    {
        $this->seat(['table_uuid' => $this->table->uuid])->assertBadRequest();
    }

    //| seating tests

    public function test_customer_is_seated_on_a_seat(): void
    {
        $response = $this->seat($this->getPostParameters());

        $response->assertOk();
        $this->assertApiMessage('Customer seated', $response);
        $this->assertEquals($this->table->uuid, $this->customer->fresh()->table_uuid);
        $this->assertEquals(2, $this->customer->fresh()->seat);
        $this->assertEquals(2, $response->json('data.floor.customers.0.seat'));
    }

    public function test_customer_can_move_to_another_seat(): void
    {
        $this->seat($this->getPostParameters(['seat' => 1]))->assertOk();
        $this->seat($this->getPostParameters(['seat' => 2]))->assertOk();

        $this->assertEquals(2, $this->customer->fresh()->seat);
    }

    public function test_customer_is_unseated_without_being_deleted(): void
    {
        $this->seat($this->getPostParameters())->assertOk();

        $response = $this->seat([]);

        $response->assertOk();
        $this->assertApiMessage('Customer unseated', $response);
        $this->assertNotNull($this->customer->fresh());
        $this->assertNull($this->customer->fresh()->table_uuid);
        $this->assertNull($this->customer->fresh()->seat);
    }

    //| failure tests

    public function test_taken_seat_cannot_be_used(): void
    {
        $this->createTestCustomer($this->restaurant, $this->table, 'Frodo', 2);

        $response = $this->seat($this->getPostParameters());

        $response->assertBadRequest();
        $this->assertApiMessage('That seat is already taken', $response);
        $this->assertNull($this->customer->fresh()->table_uuid);
    }

    public function test_seat_must_exist_at_the_table(): void
    {
        $response = $this->seat($this->getPostParameters(['seat' => 3]));

        $response->assertBadRequest();
        $this->assertApiMessage('Booth has no seat 3', $response);
    }

    public function test_table_of_another_restaurant_is_not_found(): void
    {
        $otherTable = $this->createTestTable($this->createTestRestaurant($this->getAdminUser(), 'Other'));

        $response = $this->seat($this->getPostParameters(['table_uuid' => $otherTable->uuid]));

        $response->assertNotFound();
        $this->assertApiMessage('Table not found', $response);
    }

    public function test_customer_of_another_restaurant_is_not_found(): void
    {
        $otherCustomer = $this->createTestCustomer($this->createTestRestaurant($this->getAdminUser(), 'Other'));

        $response = $this->seat($this->getPostParameters(), $otherCustomer);

        $response->assertNotFound();
        $this->assertApiMessage('Customer not found', $response);
    }
}
