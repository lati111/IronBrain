<?php

namespace Tests\Traits\Operations;

use App\Models\AbstractModel;

trait PostOperation
{
    /** @var AbstractModel The item that the get operation is supposed to edit */
    protected AbstractModel $item;

    /** @inheritDoc */
    protected function getOperationType(): string
    {
        return 'POST';
    }

    /**
     * Test that a post operation is performed correctly
     * @return void
     */
    public function test_operation(): void
    {
        $params = $this->getPostParameters();

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), $params);

        $response->assertOk();
    }
}
