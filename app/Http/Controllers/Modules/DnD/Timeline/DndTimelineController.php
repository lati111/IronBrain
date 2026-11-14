<?php

namespace App\Http\Controllers\Modules\DnD\Timeline;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class DndTimelineController extends Controller
{
    /**
     * Shows the fantasy timeline homepage
     * @return View Returns a View of the page
     */
    public function showOverview(): View
    {
        return $this->view('modules.dnd.timeline.home');
    }
}
