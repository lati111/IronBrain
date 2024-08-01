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
//    public function test_post_operation(): void
//    {
//        $params = $this->getPostParameters();
//
//        $response = $this
//            ->actingAs($this->getAdminUser())
//            ->post($this->getRoute(), $params, $this->getDefaultHeaders());
//
//        $response->assertOk();
//        $this->assertEquals($this->item['uuid'], $response->json()['data']['uuid']);
//        $this->assertArrayEquals($params, $response->json()['data']);
//    }
}
