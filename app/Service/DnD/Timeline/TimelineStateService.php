<?php

namespace App\Service\DnD\Timeline;

use App\Models\DnD\Timeline\Category;
use App\Models\DnD\Timeline\Event;
use App\Models\DnD\Timeline\FilterPreset;
use App\Models\DnD\Timeline\Interfaces\TimelineItemModel;
use App\Models\DnD\Timeline\Period;
use App\Models\DnD\Timeline\Timeline;
use App\Models\DnD\Timeline\Timespan;

class TimelineStateService
{
    /**
     * Builds the state of a timeline as drawn on its page: its settings, its categories, and its events, timespans and
     * periods in order of when they take place. Sources are left out, as they are kept out of the UI
     * @param Timeline $timeline The timeline to build the state for
     * @return array The timeline state
     */
    public function getState(Timeline $timeline): array
    {
        $categories = Category::where('timeline_uuid', $timeline->uuid)->orderBy('name')->get();
        $events = Event::where('timeline_uuid', $timeline->uuid)->orderBy('year')->orderBy('name')->get();
        $timespans = Timespan::where('timeline_uuid', $timeline->uuid)->orderBy('start_year')->orderBy('end_year')->get();
        $periods = Period::where('timeline_uuid', $timeline->uuid)->orderBy('start_year')->orderBy('end_year')->get();
        $presets = FilterPreset::where('timeline_uuid', $timeline->uuid)->orderBy('name')->get();

        return [
            'settings' => [
                'current_year' => $timeline->current_year,
                'display_start_year' => $timeline->display_start_year,
                'display_end_year' => $timeline->display_end_year,
            ],
            'presets' => $presets->map(fn (FilterPreset $preset) => [
                'uuid' => $preset->uuid,
                'name' => $preset->name,
                'blacklist' => $preset->getList('blacklist'),
                'whitelist' => $preset->getList('whitelist'),
                'highlight' => $preset->getList('highlight'),
            ])->values()->all(),
            'categories' => $categories->map(fn (Category $category) => [
                'uuid' => $category->uuid,
                'name' => $category->name,
                'description' => $category->description,
                'color' => $category->color,
                'hidden_by_default' => $category->hidden_by_default,
            ])->values()->all(),
            'events' => $events->map(fn (Event $event) => $this->getItem($event) + [
                'year' => $event->year,
            ])->values()->all(),
            'timespans' => $timespans->map(fn (Timespan $timespan) => $this->getItem($timespan) + [
                'start_year' => $timespan->start_year,
                'end_year' => $timespan->end_year,
            ])->values()->all(),
            'periods' => $periods->map(fn (Period $period) => $this->getItem($period) + [
                'start_year' => $period->start_year,
                'end_year' => $period->end_year,
            ])->values()->all(),
        ];
    }

    /**
     * Gets the details all items share
     * @param TimelineItemModel $item The item
     * @return array The details
     */
    private function getItem(TimelineItemModel $item): array
    {
        return [
            'uuid' => $item->uuid,
            'category_uuid' => $item->category_uuid,
            'name' => $item->name,
            'description' => $item->description,
            'tags' => $item->getTags(),
        ];
    }
}
