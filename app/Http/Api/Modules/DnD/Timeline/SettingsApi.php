<?php
namespace App\Http\Api\Modules\DnD\Timeline;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The settings of a timeline, each of which is optional
 */
class SettingsApi extends AbstractTimelineApi
{
    /** @var string[] The settings, which are all years */
    private const array SETTINGS = ['current_year', 'display_start_year', 'display_end_year'];

    /**
     * Saves the settings of a timeline. A setting that is left out or empty is cleared
     * @param Request $request The request parameters as given by Laravel
     * @param string $timeline_uuid The uuid of the timeline
     * @return JsonResponse The updated timeline state in JSON format
     */
    public function editSettings(Request $request, string $timeline_uuid): JsonResponse
    {
        $timeline = $this->getTimeline($timeline_uuid);
        if ($timeline === null) {
            return $this->notFound('Timeline');
        }

        $year = sprintf('nullable|integer|between:%d,%d', -self::MAX_YEAR, self::MAX_YEAR);
        $rules = array_fill_keys(self::SETTINGS, $year);
        // Only compared when both ends of the range are given, as either may be left open
        if ($request->filled('display_start_year')) {
            $rules['display_end_year'] .= '|gte:display_start_year';
        }

        $invalid = $this->validateRequest($request, $rules);
        if ($invalid !== null) {
            return $invalid;
        }

        foreach (self::SETTINGS as $setting) {
            $timeline->{$setting} = $request->filled($setting) ? (int) $request->get($setting) : null;
        }
        $timeline->save();

        return $this->respondWithState(Response::HTTP_OK, 'Settings saved', $timeline);
    }
}
