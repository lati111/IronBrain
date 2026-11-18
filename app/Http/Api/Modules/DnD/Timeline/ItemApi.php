<?php
namespace App\Http\Api\Modules\DnD\Timeline;

use App\Enum\DnD\Timeline\TimelineItemType;
use App\Models\DnD\Timeline\Category;
use App\Models\DnD\Timeline\Interfaces\TimelineItemModel;
use App\Models\DnD\Timeline\Timeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * The events, timespans and periods that make up a timeline
 */
class ItemApi extends AbstractTimelineApi
{
    /**
     * Gets the state of a timeline
     * @param string $timeline_uuid The uuid of the timeline
     * @return JsonResponse The timeline state in JSON format
     */
    public function getItems(string $timeline_uuid): JsonResponse
    {
        $timeline = $this->getTimeline($timeline_uuid);
        if ($timeline === null) {
            return $this->notFound('Timeline');
        }

        return $this->respondWithState(Response::HTTP_OK, 'Timeline retrieved', $timeline);
    }

    /**
     * Creates a new event, timespan or period in a timeline
     * @param Request $request The request parameters as given by Laravel
     * @param string $timeline_uuid The uuid of the timeline
     * @return JsonResponse The uuid of the created item and the updated timeline state in JSON format
     */
    public function createItem(Request $request, string $timeline_uuid): JsonResponse
    {
        $timeline = $this->getTimeline($timeline_uuid);
        if ($timeline === null) {
            return $this->notFound('Timeline');
        }

        $invalid = $this->validateRequest($request, ['type' => ['required', Rule::in(TimelineItemType::values())]]);
        if ($invalid !== null) {
            return $invalid;
        }

        $type = TimelineItemType::from($request->get('type'));
        $invalid = $this->validateRequest($request, $this->getItemRules($type, $timeline));
        if ($invalid !== null) {
            return $invalid;
        }

        $model = $type->model();
        /** @var TimelineItemModel $item */
        $item = new $model();
        $item->timeline_uuid = $timeline->uuid;
        $this->fillItem($item, $type, $request, $timeline);
        $item->save();

        return $this->respondWithState(Response::HTTP_CREATED, $type->label() . ' created', $timeline, ['uuid' => $item->uuid]);
    }

    /**
     * Updates an event, timespan or period. Its type can't be changed
     * @param Request $request The request parameters as given by Laravel
     * @param string $timeline_uuid The uuid of the timeline
     * @param string $type The type of the item
     * @param string $item_uuid The uuid of the item
     * @return JsonResponse The updated timeline state in JSON format
     */
    public function editItem(Request $request, string $timeline_uuid, string $type, string $item_uuid): JsonResponse
    {
        $timeline = $this->getTimeline($timeline_uuid);
        $type = TimelineItemType::tryFrom($type);
        $item = $this->findItem($timeline, $type, $item_uuid);
        if ($item === null) {
            return $this->notFound($timeline === null ? 'Timeline' : 'Item');
        }

        $invalid = $this->validateRequest($request, $this->getItemRules($type, $timeline));
        if ($invalid !== null) {
            return $invalid;
        }

        $this->fillItem($item, $type, $request, $timeline);
        $item->save();

        return $this->respondWithState(Response::HTTP_OK, $type->label() . ' updated', $timeline);
    }

    /**
     * Deletes an event, timespan or period
     * @param string $timeline_uuid The uuid of the timeline
     * @param string $type The type of the item
     * @param string $item_uuid The uuid of the item
     * @return JsonResponse The updated timeline state in JSON format
     */
    public function deleteItem(string $timeline_uuid, string $type, string $item_uuid): JsonResponse
    {
        $timeline = $this->getTimeline($timeline_uuid);
        $type = TimelineItemType::tryFrom($type);
        $item = $this->findItem($timeline, $type, $item_uuid);
        if ($item === null) {
            return $this->notFound($timeline === null ? 'Timeline' : 'Item');
        }

        $item->delete();

        return $this->respondWithState(Response::HTTP_OK, $type->label() . ' deleted', $timeline);
    }

    /**
     * Gets an item of a timeline
     * @param Timeline|null $timeline The timeline, or null when the user has no access to it
     * @param TimelineItemType|null $type The type of the item, or null when it isn't a valid type
     * @param string $uuid The uuid of the item
     * @return TimelineItemModel|null The item, or null when it does not belong to the timeline
     */
    private function findItem(?Timeline $timeline, ?TimelineItemType $type, string $uuid): ?TimelineItemModel
    {
        if ($timeline === null || $type === null) {
            return null;
        }

        return $type->model()::where('uuid', $uuid)->where('timeline_uuid', $timeline->uuid)->first();
    }

    /**
     * Gets the validation rules for an item
     * @param TimelineItemType $type The type of the item
     * @param Timeline $timeline The timeline the item belongs to
     * @return array The validation rules
     */
    private function getItemRules(TimelineItemType $type, Timeline $timeline): array
    {
        $year = sprintf('required|integer|between:%d,%d', -self::MAX_YEAR, self::MAX_YEAR);
        $rules = [
            'name'          => 'required|string|max:128',
            'description'   => 'nullable|string|max:4096',
            // A category that doesn't exist yet is created along with the item, which then needs a color for it
            'category'       => 'required|string|max:64',
            'category_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/',
                Rule::requiredIf(fn () => $this->findCategory($timeline, (string) request()->get('category')) === null)],
            'tags'          => 'nullable|string|max:1024',
        ];

        if ($type->isRange()) {
            $rules['start_year'] = $year;
            $rules['end_year'] = $year . '|gte:start_year';
        } else {
            $rules['year'] = $year;
        }

        return $rules;
    }

    /**
     * Fills an item with the request parameters. The source is left as is, as it is kept out of the UI. Does not save
     * @param TimelineItemModel $item The item
     * @param TimelineItemType $type The type of the item
     * @param Request $request The request parameters as given by Laravel
     * @param Timeline $timeline The timeline the item belongs to
     */
    private function fillItem(TimelineItemModel $item, TimelineItemType $type, Request $request, Timeline $timeline): void
    {
        $item->name          = $request->get('name');
        $item->description   = $request->get('description');
        $item->category_uuid = ($this->findCategory($timeline, $request->get('category')) ?? $this->createCategory($timeline, $request))->uuid;
        $item->setTags(explode(',', (string) $request->get('tags', '')));

        if ($type->isRange()) {
            $item->start_year = (int) $request->get('start_year');
            $item->end_year   = (int) $request->get('end_year');
        } else {
            $item->year = (int) $request->get('year');
        }
    }

    /**
     * Gets a category of a timeline by its name
     * @param Timeline $timeline The timeline
     * @param string $name The name of the category
     * @return Category|null The category, or null when the timeline has none by that name
     */
    private function findCategory(Timeline $timeline, string $name): ?Category
    {
        return Category::where('timeline_uuid', $timeline->uuid)->where('name', trim($name))->first();
    }

    /**
     * Creates the category named in the request, which has been validated to have a color
     * @param Timeline $timeline The timeline to create the category in
     * @param Request $request The request parameters as given by Laravel
     * @return Category The created category
     */
    private function createCategory(Timeline $timeline, Request $request): Category
    {
        $category = new Category();
        $category->timeline_uuid = $timeline->uuid;
        $category->name          = trim($request->get('category'));
        $category->color         = strtolower($request->get('category_color'));
        $category->save();

        return $category;
    }
}
