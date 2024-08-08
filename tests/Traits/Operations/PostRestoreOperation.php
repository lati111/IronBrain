<?php

namespace Tests\Traits\Operations;

use App\Models\AbstractModel;
use App\Models\Administration\Klant;
use Carbon\Carbon;

trait PostRestoreOperation
{
    /** @var AbstractModel The item that the get operation is supposed to edit */
    protected AbstractModel $item;

    /** @inheritDoc */
    protected function getOperationType(): string
    {
        return 'POST_RESTORE';
    }

    public function test_already_active(): void
    {
        $item = $this->createRandomEntities($this->getModel()::class, 1)->first();

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute($item), [], $this->getDefaultHeaders());

        $response->assertOk();
        $this->assertNotNull($this->getModel()->where('uuid', $item['uuid'])->first());
    }

    /**
     * Test that an archive operation is performed correctly
     * @return void
     */
    public function test_restore_operation(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), [], $this->getDefaultHeaders());

        $response->assertOk();
        $this->assertNotNull($this->getModel()->where('uuid', $this->item['uuid'])->first());
    }
}
