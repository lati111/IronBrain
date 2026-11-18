<?php

namespace Tests\Feature\Api\DnD\Timeline\ItemApi;

use App\Models\DnD\Timeline\Event;
use App\Models\DnD\Timeline\Period;
use App\Models\DnD\Timeline\Timeline;
use Tests\Feature\Api\AbstractApiFeatureTester;
use Tests\Traits\DnD\TimelineTestHelper;

class DeleteItemTest extends AbstractApiFeatureTester
{
    use TimelineTestHelper;

    private Timeline $timeline;
    private Period $period;

    public function setUp(): void
    {
        parent::setUp();
        $this->timeline = $this->createTestTimeline($this->getAdminUser());
        $this->period = $this->createTestPeriod($this->timeline, null, -500, 300);
    }

    protected function getOperationType(): string
    {
        return 'POST';
    }

    protected function getRoute(): string
    {
        return route('api.dnd.timeline.item.delete', ['timeline_uuid' => $this->timeline->uuid, 'type' => 'period', 'item_uuid' => $this->period->uuid]);
    }

    protected function getModel(): Period
    {
        return new Period();
    }

    public function test_item_is_deleted(): void
    {
        $event = $this->createTestEvent($this->timeline, null, 1);

        $response = $this->getHttpClient($this->getOperationUser())->post($this->getRoute());

        $response->assertOk();
        $this->assertApiMessage('Period deleted', $response);
        $this->assertFalse(Period::where('uuid', $this->period->uuid)->exists());
        $this->assertTrue(Event::where('uuid', $event->uuid)->exists());
        $this->assertEquals([], $response->json('data.timeline.periods'));
    }

    public function test_item_of_other_user_is_not_deleted(): void
    {
        $timeline = $this->createTestTimeline($this->getRandomUser(), 'Someone else');
        $period = $this->createTestPeriod($timeline, null, 1, 2);

        $response = $this->getHttpClient($this->getOperationUser())->post(
            route('api.dnd.timeline.item.delete', ['timeline_uuid' => $timeline->uuid, 'type' => 'period', 'item_uuid' => $period->uuid]),
        );

        $response->assertNotFound();
        $this->assertApiMessage('Timeline not found', $response);
        $this->assertTrue(Period::where('uuid', $period->uuid)->exists());
    }
}
