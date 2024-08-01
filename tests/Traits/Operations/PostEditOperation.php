<?php

namespace Tests\Traits\Operations;

use App\Models\AbstractModel;

trait PostEditOperation
{
    /** @var AbstractModel The item that the get operation is supposed to edit */
    protected AbstractModel $item;

    /** @inheritDoc */
    protected function getOperationType(): string
    {
        return 'POST_EDIT';
    }

    /**
     * Test that a post operation with null parameters is performed correctly
     * @return void
     */
    public function test_null_parameters_post_operation(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), [], $this->getDefaultHeaders());

        $response->assertOk();
        $updatedItem = $this->getModel()->where('uuid', $this->item->uuid)->first();
        $this->assertArrayEquals($this->item->toArray(), $updatedItem->toArray());
    }

    /**
     * Test that a post operation is performed correctly
     * @return void
     */
    public function test_post_operation(): void
    {
        $params = $this->getPostParameters();

        $response = $this
            ->actingAs($this->getAdminUser())
            ->post($this->getRoute(), $params, $this->getDefaultHeaders());

        $response->assertOk();
        $updatedItem = $this->getModel()->where('uuid', $this->item->uuid)->first();
        $this->assertArrayEquals($params, $updatedItem->toArray());
    }
}
