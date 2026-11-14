<?php

namespace Tests\Feature\Api\DnD\Timeline\ItemApi;

use App\Models\DnD\Timeline\Timeline;
use Tests\Feature\Api\AbstractApiFeatureTester;
use Tests\Traits\DnD\TimelineTestHelper;

class GetItemsTest extends AbstractApiFeatureTester
{
    use TimelineTestHelper;

    private Timeline $timeline;

    public function setUp(): void
    {
        parent::setUp();
        $this->timeline = $this->createTestTimeline($this->getAdminUser());
    }

    protected function getOperationType(): string
    {
        return 'GET';
    }

    protected function getRoute(): string
    {
        return route('api.dnd.timeline.get', ['timeline_uuid' => $this->timeline->uuid]);
    }

    protected function getModel(): Timeline
    {
        return new Timeline();
    }

    public function test_state_lists_items_in_order(): void
    {
        $category = $this->createTestCategory($this->timeline, 'Wars', '#aa3311');
        $this->createTestEvent($this->timeline, $category, 50, 'Later');
        $event = $this->createTestEvent($this->timeline, null, -20, 'Earlier');
        $event->source = 'Session 1 notes';
        $event->save();
        $this->createTestTimespan($this->timeline, $category, 1, 9);
        $this->createTestPeriod($this->timeline, null, -100, 100);

        $response = $this->getHttpClient($this->getOperationUser())->get($this->getRoute());

        $response->assertOk();
        $this->assertEquals(['current_year' => null, 'display_start_year' => null, 'display_end_year' => null],
            $response->json('data.timeline.settings'));
        $this->assertEquals([['uuid' => $category->uuid, 'name' => 'Wars', 'description' => null, 'color' => '#aa3311']],
            $response->json('data.timeline.categories'));
        $this->assertEquals(['Earlier', 'Later'], array_column($response->json('data.timeline.events'), 'name'));
        $this->assertEquals(-20, $response->json('data.timeline.events.0.year'));
        $this->assertArrayNotHasKey('source', $response->json('data.timeline.events.0'));
        $this->assertEquals(1, $response->json('data.timeline.timespans.0.start_year'));
        $this->assertEquals(100, $response->json('data.timeline.periods.0.end_year'));
    }

    public function test_timeline_of_other_user_is_not_found(): void
    {
        $timeline = $this->createTestTimeline($this->getRandomUser(), 'Someone else');

        $response = $this->getHttpClient($this->getOperationUser())
            ->get(route('api.dnd.timeline.get', ['timeline_uuid' => $timeline->uuid]));

        $response->assertNotFound();
    }
}
