<?php

namespace Tests\Feature\Api\DnD\Restaurant\FloorApi;

use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\Table;
use Tests\Feature\Api\AbstractApiFeatureTester;
use Tests\Traits\DnD\RestaurantTestHelper;

class DeleteTableTest extends AbstractApiFeatureTester
{
    use RestaurantTestHelper;

    private Restaurant $restaurant;
    private Table $table;

    public function setUp(): void
    {
        parent::setUp();
        $this->restaurant = $this->createTestRestaurant($this->getAdminUser());
        $this->table = $this->createTestTable($this->restaurant);
    }

    protected function getOperationType(): string
    {
        return 'POST';
    }

    protected function getRoute(?Table $table = null): string
    {
        return route('api.dnd.restaurant.floor.table.delete', [
            'restaurant_uuid' => $this->restaurant->uuid,
            'table_uuid'      => ($table ?? $this->table)->uuid,
        ]);
    }

    protected function getModel(): Table
    {
        return new Table();
    }

    public function test_table_is_deleted_and_its_customers_are_unseated(): void
    {
        $customer = $this->createTestCustomer($this->restaurant, $this->table);

        $response = $this->getHttpClient($this->getOperationUser())->post($this->getRoute());

        $response->assertOk();
        $this->assertFalse(Table::where('uuid', $this->table->uuid)->exists());
        $this->assertNotNull($customer->fresh());
        $this->assertNull($customer->fresh()->table_uuid);
    }

    public function test_table_of_another_restaurant_is_not_found(): void
    {
        $otherTable = $this->createTestTable($this->createTestRestaurant($this->getAdminUser(), 'Other'));

        $response = $this->getHttpClient($this->getOperationUser())->post($this->getRoute($otherTable));

        $response->assertNotFound();
        $this->assertApiMessage('Table not found', $response);
        $this->assertTrue(Table::where('uuid', $otherTable->uuid)->exists());
    }
}
