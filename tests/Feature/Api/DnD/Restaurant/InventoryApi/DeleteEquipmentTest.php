<?php

namespace Tests\Feature\Api\DnD\Restaurant\InventoryApi;

use App\Models\DnD\Restaurant\Equipment;
use App\Models\DnD\Restaurant\OwnedEquipment;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\StorageEquipment;
use Tests\Feature\Api\AbstractApiFeatureTester;
use Tests\Traits\DnD\RestaurantTestHelper;

class DeleteEquipmentTest extends AbstractApiFeatureTester
{
    use RestaurantTestHelper;

    private Restaurant $restaurant;
    private Equipment $equipment;

    public function setUp(): void
    {
        parent::setUp();
        $this->restaurant = $this->createTestRestaurant($this->getAdminUser());
        $this->equipment = $this->createTestEquipment($this->restaurant, 1, 'Stove');
    }

    protected function getOperationType(): string
    {
        return 'POST';
    }

    protected function getRoute(?Restaurant $restaurant = null, ?Equipment $equipment = null): string
    {
        return route('api.dnd.restaurant.inventory.equipment.delete', [
            'restaurant_uuid' => ($restaurant ?? $this->restaurant)->uuid,
            'equipment_uuid'  => ($equipment ?? $this->equipment)->uuid,
        ]);
    }

    protected function getModel(): Equipment
    {
        return new Equipment();
    }

    public function test_stored_equipment_is_deleted_and_its_children_lose_their_parent(): void
    {
        $storage = $this->createTestStorage($this->restaurant);
        $this->storeEquipment($storage, $this->equipment, 1);

        $child = $this->createTestEquipment($this->restaurant, 1, 'Burner');
        $child->parent_uuid = $this->equipment->uuid;
        $child->save();

        $response = $this->getHttpClient($this->getOperationUser())->post($this->getRoute());

        $response->assertOk();
        $this->assertFalse(Equipment::where('uuid', $this->equipment->uuid)->exists());
        $this->assertFalse(StorageEquipment::where('equipment_uuid', $this->equipment->uuid)->exists());
        $this->assertNull(Equipment::where('uuid', $child->uuid)->value('parent_uuid'));
    }

    public function test_owned_equipment_is_deleted(): void
    {
        $tool = $this->createTestEquipment($this->restaurant, 1, 'Ladle', false);
        $this->ownEquipment($this->restaurant, $tool, 2);

        $this->getHttpClient($this->getOperationUser())->post($this->getRoute(null, $tool))->assertOk();

        $this->assertFalse(OwnedEquipment::where('equipment_uuid', $tool->uuid)->exists());
    }

    public function test_equipment_of_another_restaurant_is_not_found(): void
    {
        $otherEquipment = $this->createTestEquipment($this->createTestRestaurant($this->getAdminUser(), 'Other'));

        $response = $this->getHttpClient($this->getOperationUser())->post($this->getRoute(null, $otherEquipment));

        $response->assertNotFound();
        $this->assertApiMessage('Equipment not found', $response);
    }

    public function test_restaurant_without_access_is_not_found(): void
    {
        $restaurant = $this->createTestRestaurant($this->getRandomUser(), 'Someone else');

        $response = $this->getHttpClient($this->getOperationUser())->post($this->getRoute($restaurant));

        $response->assertNotFound();
        $this->assertApiMessage('Restaurant not found', $response);
    }
}
