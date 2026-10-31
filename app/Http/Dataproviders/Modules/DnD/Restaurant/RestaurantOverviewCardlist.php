<?php
namespace App\Http\Dataproviders\Modules\DnD\Restaurant;

use App\Http\Dataproviders\AbstractCardlist;
use App\Http\Dataproviders\Traits\HasPages;
use App\Models\Auth\User;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\RestaurantUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Lati111\LaravelDataproviders\Traits\Dataprovider;
use Lati111\LaravelDataproviders\Traits\Paginatable;
use Lati111\LaravelDataproviders\Traits\Searchable;

class RestaurantOverviewCardlist extends AbstractCardlist
{
    use Dataprovider, Paginatable, HasPages, Searchable;

    /** { @inheritdoc } */
    public function data(Request $request): JsonResponse
    {
        $user = Auth::user();

        $data = $this->getData($request)
            ->get()
            ->map(function ($restaurant) use ($user) {
                $restaurant['is_owner'] = $restaurant->owner_uuid === $user->uuid ? 1 : 0;
                $restaurant['money'] = Restaurant::formatMoney($restaurant->getTotalBits());
                $restaurant['url'] =route('dnd.restaurant.management', ['restaurant_uuid' => $restaurant->uuid]);
                return $restaurant;
            });

        return $this->respondWithItems($request, $data);
    }

    /** { @inheritdoc } */
    protected function getContent(Request $request, bool $dataQuery = true): Builder
    {
        $user = Auth::user();

        return Restaurant::query()
            ->join(User::getTableName() . ' as owner', Restaurant::TABLE_NAME . '.owner_uuid', '=', 'owner.uuid')
            ->where(function (Builder $query) use ($user) {
                $query->where(Restaurant::TABLE_NAME . '.owner_uuid', $user->uuid)
                    ->orWhereIn(Restaurant::TABLE_NAME . '.uuid', RestaurantUser::query()
                        ->select('restaurant_uuid')
                        ->where('user_uuid', $user->uuid));
            })
            ->orderBy(Restaurant::TABLE_NAME . '.name')
            ->select([
                Restaurant::TABLE_NAME . '.uuid',
                Restaurant::TABLE_NAME . '.owner_uuid',
                Restaurant::TABLE_NAME . '.name',
                Restaurant::TABLE_NAME . '.bits',
                Restaurant::TABLE_NAME . '.copper',
                Restaurant::TABLE_NAME . '.silver',
                Restaurant::TABLE_NAME . '.gold',
                'owner.username as owner_name',
            ]);
    }

    /** { @inheritdoc } */
    public function getSearchFields(): array
    {
        return [Restaurant::TABLE_NAME . '.name'];
    }
}
