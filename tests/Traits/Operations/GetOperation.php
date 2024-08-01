<?php

namespace Tests\Traits\Operations;

use App\Models\AbstractModel;

trait GetOperation
{
    /** @var AbstractModel The item that the get operation is supposed to retrieve */
    protected AbstractModel $retrievableItem;

    /** @inheritDoc */
    protected function getOperationType(): string
    {
        return 'GET';
    }

    /**
     * Test that a get all operation is performed correctly
     * @return void
     */
    public function test_get_operation(): void
    {
        $items = $this->createRandomEntities($this->getModel()::factory(), 2);
        /** @var AbstractModel $item */
        $item = $items->last();

        $response = $this
            ->actingAs($this->getAdminUser())
            ->get($this->getRoute($item), $this->getDefaultHeaders());

        $response->assertOk();
        $this->assertArrayEquals($item->toArray(), $response->json()['data']);
    }

    /**
     * Test that a get all operation is performed correctly
     * @return void
     */
    public function test_get_null(): void
    {
        $item = $this->getModel();
        $item['uuid'] = $this->getFalseUuid($this->getModel()::class);

        $response = $this
            ->actingAs($this->getAdminUser())
            ->get($this->getRoute($item), $this->getDefaultHeaders());

        $response->assertNotFound();
        $this->assertEquals($this->getNotFoundMessage(), $response->json()['message']);
    }
}
