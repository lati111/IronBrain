<?php
namespace App\Http\Api\Modules\DnD\Timeline;

use App\Models\DnD\Timeline\FilterPreset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The saved presets of the tag lists a timeline is filtered and highlighted by
 */
class PresetApi extends AbstractTimelineApi
{
    /**
     * Saves a preset, replacing the lists of the preset of the same name when there is one
     * @param Request $request The request parameters as given by Laravel
     * @param string $timeline_uuid The uuid of the timeline
     * @return JsonResponse The uuid of the preset and the updated timeline state in JSON format
     */
    public function savePreset(Request $request, string $timeline_uuid): JsonResponse
    {
        $timeline = $this->getTimeline($timeline_uuid);
        if ($timeline === null) {
            return $this->notFound('Timeline');
        }

        $invalid = $this->validateRequest($request, [
            'name'      => 'required|string|max:64',
            'blacklist' => 'nullable|string|max:1024',
            'whitelist' => 'nullable|string|max:1024',
            'highlight' => 'nullable|string|max:1024',
        ]);
        if ($invalid !== null) {
            return $invalid;
        }

        $name = trim($request->get('name'));
        if ($name === '') {
            return $this->respond(Response::HTTP_BAD_REQUEST, 'The name field is required.', ['name' => ['The name field is required.']]);
        }

        $preset = FilterPreset::where('timeline_uuid', $timeline->uuid)->where('name', $name)->first();
        $created = $preset === null;
        if ($created) {
            $preset = new FilterPreset();
            $preset->timeline_uuid = $timeline->uuid;
            $preset->name = $name;
        }

        foreach (FilterPreset::LISTS as $list) {
            $preset->setList($list, explode(',', (string) $request->get($list, '')));
        }
        $preset->save();

        return $this->respondWithState($created ? Response::HTTP_CREATED : Response::HTTP_OK,
            $created ? 'Preset saved' : 'Preset updated', $timeline, ['uuid' => $preset->uuid]);
    }

    /**
     * Deletes a preset
     * @param string $timeline_uuid The uuid of the timeline
     * @param string $preset_uuid The uuid of the preset
     * @return JsonResponse The updated timeline state in JSON format
     */
    public function deletePreset(string $timeline_uuid, string $preset_uuid): JsonResponse
    {
        $timeline = $this->getTimeline($timeline_uuid);
        $preset = $timeline === null ? null
            : FilterPreset::where('uuid', $preset_uuid)->where('timeline_uuid', $timeline->uuid)->first();
        if ($preset === null) {
            return $this->notFound($timeline === null ? 'Timeline' : 'Preset');
        }

        $preset->delete();

        return $this->respondWithState(Response::HTTP_OK, 'Preset deleted', $timeline);
    }
}
