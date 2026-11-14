<?php

namespace Tests\Feature\Dataprovider\DnD\Timeline;

use Tests\Feature\AbstractFeatureTester;
use Tests\Traits\DnD\TimelineTestHelper;

class TimelineOverviewCardlistTest extends AbstractFeatureTester
{
    use TimelineTestHelper;

    public function test_lists_owned_timelines_only(): void
    {
        $admin = $this->getAdminUser();

        $owned = $this->createTestTimeline($admin, 'Owned Timeline');
        $owned->cover_image = 'modules/dnd/timeline/cover/test.png';
        $owned->save();
        $uncovered = $this->createTestTimeline($admin, 'Timeline Without Cover');
        $other = $this->createTestTimeline($this->getRandomUser(), 'Someone Else\'s Timeline');

        $response = $this->withHeaders(['Accept' => 'application/json'])
            ->actingAs($admin)
            ->get(route('data.dnd.timeline.overview', ['perpage' => 30]));

        $response->assertOk();
        $items = collect($response->json('data.items'))->keyBy('uuid');

        $this->assertTrue($items->has($owned->uuid));
        $this->assertTrue($items->has($uncovered->uuid));
        $this->assertFalse($items->has($other->uuid));

        $this->assertEquals(route('dnd.timeline.show', ['timeline_uuid' => $owned->uuid]), $items[$owned->uuid]['url']);
        $this->assertEquals(1, $items[$owned->uuid]['has_cover']);
        $this->assertEquals(asset('img/modules/dnd/timeline/cover/test.png'), $items[$owned->uuid]['cover_url']);

        // Left out, so the cardlist doesn't try to load an image
        $this->assertEquals(0, $items[$uncovered->uuid]['has_cover']);
        $this->assertArrayNotHasKey('cover_url', $items[$uncovered->uuid]);
    }
}
