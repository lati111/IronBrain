<?php
namespace App\Http\Api\Modules\DnD\Timeline;

use App\Http\Api\AbstractApi;
use App\Http\Api\Modules\DnD\Restaurant\Traits\RespondsWithErrors;
use App\Models\DnD\Timeline\Timeline;
use App\Service\DnD\Timeline\TimelineStateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * The parts of a timeline managed on its page, which respond with the updated timeline state
 */
abstract class AbstractTimelineApi extends AbstractApi
{
    use RespondsWithErrors;

    /** @var int The furthest a year may be from year zero, keeping it within the signed integer columns */
    protected const int MAX_YEAR = 2000000000;

    public function __construct(protected readonly TimelineStateService $stateService)
    {
    }

    /**
     * Gets a timeline the current user has access to
     * @param string $uuid The uuid of the timeline
     * @return Timeline|null The timeline, or null if it does not exist or the user has no access
     */
    protected function getTimeline(string $uuid): ?Timeline
    {
        $timeline = Timeline::where('uuid', $uuid)->first();
        if ($timeline === null || $timeline->hasAccess(Auth::user()) === false) {
            return null;
        }

        return $timeline;
    }

    /**
     * Responds with the updated state of the timeline
     * @param int $status The status code
     * @param string $message The message
     * @param Timeline $timeline The timeline
     * @param array $data Anything to respond with ahead of the timeline state, such as the uuid of what was created
     * @return JsonResponse The data and the timeline state in JSON format
     */
    protected function respondWithState(int $status, string $message, Timeline $timeline, array $data = []): JsonResponse
    {
        return $this->respond($status, $message, $data + ['timeline' => $this->stateService->getState($timeline)]);
    }
}
