<?php

namespace Tests\Traits\DnD;

use App\Models\Auth\User;
use App\Models\DnD\Timeline\Category;
use App\Models\DnD\Timeline\Event;
use App\Models\DnD\Timeline\Period;
use App\Models\DnD\Timeline\Timeline;
use App\Models\DnD\Timeline\Timespan;

trait TimelineTestHelper
{
    protected function createTestTimeline(User $owner, string $name = 'Test Timeline'): Timeline
    {
        $timeline = new Timeline();
        $timeline->owner_uuid = $owner->uuid;
        $timeline->name = $name;
        $timeline->save();
        return $timeline;
    }

    protected function createTestCategory(Timeline $timeline, string $name = 'Test Category', string $color = '#aa3311'): Category
    {
        $category = new Category();
        $category->timeline_uuid = $timeline->uuid;
        $category->name = $name;
        $category->color = $color;
        $category->save();
        return $category;
    }

    protected function createTestEvent(Timeline $timeline, ?Category $category, int $year, string $name = 'Test Event'): Event
    {
        $event = new Event();
        $event->timeline_uuid = $timeline->uuid;
        $event->category_uuid = $category?->uuid;
        $event->name = $name;
        $event->year = $year;
        $event->save();
        return $event;
    }

    protected function createTestTimespan(Timeline $timeline, ?Category $category, int $startYear, int $endYear, string $name = 'Test Timespan'): Timespan
    {
        $timespan = new Timespan();
        $this->fillTestSpan($timespan, $timeline, $category, $startYear, $endYear, $name);
        return $timespan;
    }

    protected function createTestPeriod(Timeline $timeline, ?Category $category, int $startYear, int $endYear, string $name = 'Test Period'): Period
    {
        $period = new Period();
        $this->fillTestSpan($period, $timeline, $category, $startYear, $endYear, $name);
        return $period;
    }

    private function fillTestSpan(Timespan $span, Timeline $timeline, ?Category $category, int $startYear, int $endYear, string $name): void
    {
        $span->timeline_uuid = $timeline->uuid;
        $span->category_uuid = $category?->uuid;
        $span->name = $name;
        $span->start_year = $startYear;
        $span->end_year = $endYear;
        $span->save();
    }
}
