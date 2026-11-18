<?php
namespace App\Http\Api\Modules\DnD\Timeline;

use App\Models\DnD\Timeline\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * The categories the items of a timeline are sorted into. They are created along with the items using them
 */
class CategoryApi extends AbstractTimelineApi
{
    /**
     * Updates a category
     * @param Request $request The request parameters as given by Laravel
     * @param string $timeline_uuid The uuid of the timeline
     * @param string $category_uuid The uuid of the category
     * @return JsonResponse The updated timeline state in JSON format
     */
    public function editCategory(Request $request, string $timeline_uuid, string $category_uuid): JsonResponse
    {
        $timeline = $this->getTimeline($timeline_uuid);
        $category = $timeline === null ? null
            : Category::where('uuid', $category_uuid)->where('timeline_uuid', $timeline->uuid)->first();
        if ($category === null) {
            return $this->notFound($timeline === null ? 'Timeline' : 'Category');
        }

        $invalid = $this->validateRequest($request, [
            'name'        => ['required', 'string', 'max:64', Rule::unique(Category::TABLE_NAME, 'name')
                ->where('timeline_uuid', $timeline->uuid)->ignore($category->uuid, 'uuid')],
            'description' => 'nullable|string|max:2048',
            'color'       => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'hidden_by_default' => 'nullable|boolean',
        ]);
        if ($invalid !== null) {
            return $invalid;
        }

        $category->name        = trim($request->get('name'));
        $category->description = $request->get('description');
        $category->color       = strtolower($request->get('color'));
        // A checkbox that isn't ticked isn't sent, so leaving it out means the category is shown by default
        $category->hidden_by_default = $request->boolean('hidden_by_default');
        $category->save();

        return $this->respondWithState(Response::HTTP_OK, 'Category updated', $timeline);
    }

    /**
     * Deletes a category. Its items stay on the timeline, without a category
     * @param string $timeline_uuid The uuid of the timeline
     * @param string $category_uuid The uuid of the category
     * @return JsonResponse The updated timeline state in JSON format
     */
    public function deleteCategory(string $timeline_uuid, string $category_uuid): JsonResponse
    {
        $timeline = $this->getTimeline($timeline_uuid);
        $category = $timeline === null ? null
            : Category::where('uuid', $category_uuid)->where('timeline_uuid', $timeline->uuid)->first();
        if ($category === null) {
            return $this->notFound($timeline === null ? 'Timeline' : 'Category');
        }

        // The items are left without a category by the database
        $category->delete();

        return $this->respondWithState(Response::HTTP_OK, 'Category deleted', $timeline);
    }
}
