<?php

namespace App\Http\Controllers\Modules\DnD\Timeline;

use App\Http\Controllers\Controller;
use App\Models\DnD\Timeline\Timeline;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DndTimelineController extends Controller
{
    /**
     * Shows the fantasy timeline homepage, listing the user's timelines
     * @return View Returns a View of the page
     */
    public function showOverview(): View
    {
        return $this->view('modules.dnd.timeline.home');
    }

    /**
     * Shows a timeline
     * @param string $timeline_uuid The uuid of the timeline
     * @return View Returns a View of the page
     */
    public function showTimeline(string $timeline_uuid): View
    {
        return $this->view('modules.dnd.timeline.timeline', [
            'timeline' => $this->getTimeline($timeline_uuid),
        ]);
    }

    /**
     * Gets a timeline the current user has access to, aborting with a 404 otherwise
     * @param string $uuid The uuid of the timeline
     * @return Timeline The timeline
     */
    private function getTimeline(string $uuid): Timeline
    {
        $timeline = Timeline::where('uuid', $uuid)->first();
        if ($timeline === null || $timeline->hasAccess(Auth::user()) === false) {
            abort(404);
        }

        return $timeline;
    }
}
