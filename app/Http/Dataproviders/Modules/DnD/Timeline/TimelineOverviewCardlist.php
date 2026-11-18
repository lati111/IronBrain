<?php
namespace App\Http\Dataproviders\Modules\DnD\Timeline;

use App\Http\Dataproviders\AbstractCardlist;
use App\Http\Dataproviders\Traits\HasPages;
use App\Models\DnD\Timeline\Timeline;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Lati111\LaravelDataproviders\Traits\Dataprovider;
use Lati111\LaravelDataproviders\Traits\Paginatable;
use Lati111\LaravelDataproviders\Traits\Searchable;

class TimelineOverviewCardlist extends AbstractCardlist
{
    use Dataprovider, Paginatable, HasPages, Searchable;

    /** { @inheritdoc } */
    public function data(Request $request): JsonResponse
    {
        $data = $this->getData($request)
            ->get()
            ->map(function (Timeline $timeline) {
                // The cardlist would load "null" as the image, so the url is left out of timelines without a cover
                $timeline['has_cover'] = $timeline->cover_image !== null ? 1 : 0;
                if ($timeline->cover_image !== null) {
                    $timeline['cover_url'] = $timeline->getCoverUrl();
                }
                $timeline['url'] = route('dnd.timeline.show', ['timeline_uuid' => $timeline->uuid]);
                return $timeline;
            });

        return $this->respondWithItems($request, $data);
    }

    /** { @inheritdoc } */
    protected function getContent(Request $request, bool $dataQuery = true): Builder
    {
        return Timeline::query()
            ->where('owner_uuid', Auth::user()->uuid)
            ->orderBy('name')
            ->select(['uuid', 'name', 'description', 'cover_image']);
    }

    /** { @inheritdoc } */
    public function getSearchFields(): array
    {
        return ['name', 'description'];
    }
}
