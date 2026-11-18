<?php
namespace App\Http\Api\Modules\DnD\Timeline;

use App\Enum\ErrorEnum;
use App\Http\Api\AbstractApi;
use App\Models\DnD\Timeline\Timeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage as FileStorage;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class TimelineApi extends AbstractApi
{
    /**
     * Creates a new timeline owned by the current user
     * @param Request $request The request parameters as given by Laravel
     * @return JsonResponse The uuid of the created timeline in JSON format
     */
    public function createTimeline(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:64',
            'description' => 'nullable|string|max:2048',
            'cover_image' => 'nullable|image|max:5120',
        ]);

        if ($validator->fails()) {
            return $this->respond(Response::HTTP_BAD_REQUEST, ErrorEnum::VALIDATION_FAIL, $validator->errors());
        }

        $timeline = new Timeline();
        $timeline->owner_uuid  = Auth::user()->uuid;
        $timeline->name        = $request->get('name');
        $timeline->description = $request->get('description');

        if ($request->hasFile('cover_image')) {
            $timeline->cover_image = FileStorage::putFile(Timeline::COVER_IMAGE_PATH, $request->file('cover_image'));
        }

        $timeline->save();

        return $this->respond(Response::HTTP_CREATED, 'Timeline created', $timeline->uuid);
    }
}
