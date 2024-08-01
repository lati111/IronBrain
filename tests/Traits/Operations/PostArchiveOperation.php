<?php

namespace Tests\Traits\Operations;

use App\Models\AbstractModel;
use App\Models\Administration\Klant;
use Carbon\Carbon;

trait PostArchiveOperation
{
    /** @var AbstractModel The item that the get operation is supposed to edit */
    protected AbstractModel $item;

    /** @inheritDoc */
    protected function getOperationType(): string
    {
        return 'POST_ARCHIVE';
    }

    public function test_already_archived(): void
    {
        $item = $this->createRandomEntities($this->getModel()::class, 1, ['deleted_at' => Carbon::now()])->first();

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute($item), [], $this->getDefaultHeaders());

        $response->assertNotFound();
        $this->assertEquals($this->getNotFoundMessage(), $response->json()['message']);
    }

    /**
     * Test that an archive operation is performed correctly
     * @return void
     */
    public function test_archive_operation(): void
    {
        $response = $this
            ->actingAs($this->getOperationUser())
            ->post($this->getRoute(), [], $this->getDefaultHeaders());

        $response->assertOk();
        $this->assertNull($this->getModel()->where('uuid', $this->item['uuid'])->first());
    }
}
