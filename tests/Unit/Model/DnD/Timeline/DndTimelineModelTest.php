<?php

namespace Tests\Unit\Model\DnD\Timeline;

use App\Models\Auth\User;
use App\Models\DnD\Timeline\Category;
use App\Models\DnD\Timeline\Event;
use App\Models\DnD\Timeline\Period;
use App\Models\DnD\Timeline\Timespan;
use Tests\Traits\DnD\TimelineTestHelper;
use Tests\Unit\AbstractUnitTester;

class DndTimelineModelTest extends AbstractUnitTester
{
    use TimelineTestHelper;

    private function getTestUser(): User
    {
        return User::where('email', 'test@test.nl')->first();
    }

    public function test_timeline_holds_its_items(): void
    {
        $timeline = $this->createTestTimeline($this->getAdminUser());
        $category = $this->createTestCategory($timeline);
        $this->createTestEvent($timeline, $category, -120);
        $this->createTestTimespan($timeline, $category, 10, 40);
        $this->createTestPeriod($timeline, null, -500, 300);

        $this->assertEquals(1, $timeline->Categories()->count());
        $this->assertEquals(-120, $timeline->Events()->first()->year);
        $this->assertEquals(1, $timeline->Timespans()->count());
        $this->assertEquals(1, $timeline->Periods()->count());

        // Periods and timespans each keep to their own table
        $this->assertEquals(1, $category->Timespans()->count());
        $this->assertEquals(0, $category->Periods()->count());
    }

    public function test_timeline_is_only_accessible_to_its_owner(): void
    {
        $timeline = $this->createTestTimeline($this->getAdminUser());

        $this->assertTrue($timeline->hasAccess($this->getAdminUser()));
        $this->assertFalse($timeline->hasAccess($this->getTestUser()));
        $this->assertEquals($this->getAdminUser()->uuid, $timeline->getOwner()->uuid);
    }

    public function test_deleting_a_timeline_deletes_its_contents(): void
    {
        $timeline = $this->createTestTimeline($this->getAdminUser());
        $category = $this->createTestCategory($timeline);
        $event = $this->createTestEvent($timeline, $category, 1);
        $timespan = $this->createTestTimespan($timeline, $category, 1, 2);
        $period = $this->createTestPeriod($timeline, $category, 1, 200);

        $timeline->delete();

        $this->assertFalse(Category::where('uuid', $category->uuid)->exists());
        $this->assertFalse(Event::where('uuid', $event->uuid)->exists());
        $this->assertFalse(Timespan::where('uuid', $timespan->uuid)->exists());
        $this->assertFalse(Period::where('uuid', $period->uuid)->exists());
    }

    public function test_deleting_a_category_keeps_its_items(): void
    {
        $timeline = $this->createTestTimeline($this->getAdminUser());
        $category = $this->createTestCategory($timeline);
        $event = $this->createTestEvent($timeline, $category, 1);

        $category->delete();

        $event->refresh();
        $this->assertNull($event->category_uuid);
    }

    public function test_tags_are_read_and_written_as_a_list(): void
    {
        $timeline = $this->createTestTimeline($this->getAdminUser());
        $event = $this->createTestEvent($timeline, null, 1);
        $this->assertEquals([], $event->getTags());

        $event->setTags([' war', 'dwarves ', '', 'war']);
        $this->assertEquals('war,dwarves', $event->tags);
        $this->assertEquals(['war', 'dwarves'], $event->getTags());
        $this->assertTrue($event->hasTag('dwarves'));
        $this->assertFalse($event->hasTag('elves'));

        $event->tags = 'war, ,elves,';
        $this->assertEquals(['war', 'elves'], $event->getTags());

        $event->setTags([]);
        $this->assertNull($event->tags);
    }

    public function test_source_is_kept_out_of_serialization(): void
    {
        $timeline = $this->createTestTimeline($this->getAdminUser());
        $event = $this->createTestEvent($timeline, null, 1);
        $event->source = 'Session 12 notes';
        $event->save();

        $this->assertEquals('Session 12 notes', $event->fresh()->source);
        $this->assertArrayNotHasKey('source', $event->toArray());
    }

    public function test_timespan_contains_its_start_and_end_year(): void
    {
        $timeline = $this->createTestTimeline($this->getAdminUser());
        $timespan = $this->createTestTimespan($timeline, null, -10, 10);

        $this->assertTrue($timespan->contains(-10));
        $this->assertTrue($timespan->contains(0));
        $this->assertTrue($timespan->contains(10));
        $this->assertFalse($timespan->contains(11));
    }
}
