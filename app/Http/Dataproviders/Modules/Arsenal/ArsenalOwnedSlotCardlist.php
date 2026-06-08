<?php
namespace App\Http\Dataproviders\Modules\Arsenal;

use App\Enum\GenericStringEnum;
use App\Http\Dataproviders\AbstractCardlist;
use App\Models\Arsenal\Companion;
use App\Models\Arsenal\UserCompanion;
use App\Models\Arsenal\UserWarframe;
use App\Models\Arsenal\UserWeapon;
use App\Models\Arsenal\Warframe;
use App\Models\Arsenal\Weapon;
use App\Models\Auth\User;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ArsenalOwnedSlotCardlist extends AbstractCardlist
{
    private const DEFAULT_PER_PAGE = 100;

    /** { @inheritdoc } */
    public function data(Request $request): JsonResponse
    {
        $user    = Auth::user();
        $slot    = trim($request->get('slot', ''));
        $search  = trim($request->get('search', ''));
        $page    = max(1, (int) $request->get('page', 1));
        $perPage = max(1, (int) $request->get('per_page', $request->get('perpage', self::DEFAULT_PER_PAGE)));

        $items = $this->buildQuery($user, $slot, $search)
            ->forPage($page, $perPage)
            ->get()
            ->map(function ($item) {
                if ($item->icon !== null) {
                    $item->icon = asset('img/' . $item->icon);
                }
                return $item;
            });

        return $this->respond(Response::HTTP_OK, GenericStringEnum::DATA_RETRIEVED, $items);
    }

    /** { @inheritdoc } */
    public function count(Request $request): JsonResponse
    {
        $user    = Auth::user();
        $slot    = trim($request->get('slot', ''));
        $search  = trim($request->get('search', ''));
        $perPage = max(1, (int) $request->get('per_page', $request->get('perpage', self::DEFAULT_PER_PAGE)));

        $total = $this->buildQuery($user, $slot, $search)->count();
        return $this->respond(Response::HTTP_OK, GenericStringEnum::DATA_RETRIEVED, (int) ceil($total / $perPage));
    }

    private function buildQuery(User $user, string $slot, string $search): QueryBuilder
    {
        return match ($slot) {
            'warframe'  => $this->ownedItemQuery(UserWarframe::TABLE_NAME,  'uw', Warframe::TABLE_NAME,  'w',   $user, $search),
            'companion' => $this->ownedItemQuery(UserCompanion::TABLE_NAME, 'uc', Companion::TABLE_NAME, 'c',   $user, $search),
            default     => $this->ownedItemQuery(UserWeapon::TABLE_NAME,    'uw', Weapon::TABLE_NAME,    'wp',  $user, $search, ['LOWER(wp.type) = ?', [$slot]]),
        };
    }

    private function ownedItemQuery(
        string $userTable, string $userAlias,
        string $baseTable, string $baseAlias,
        User $user, string $search, ?array $extraWhere = null
    ): QueryBuilder {
        $coalesce = "COALESCE($userAlias.name, $baseAlias.name)";

        $q = DB::table("$userTable as $userAlias")
            ->join("$baseTable as $baseAlias", "$userAlias.id", '=', "$baseAlias.id")
            ->where("$userAlias.owner_uuid", $user->uuid)
            ->select([
                "$userAlias.uuid as item_uuid",
                DB::raw("$coalesce as name"),
                "$baseAlias.icon as icon",
            ]);

        if ($extraWhere !== null) {
            $q->whereRaw($extraWhere[0], $extraWhere[1]);
        }

        if ($search !== '') {
            $q->where(DB::raw($coalesce), 'LIKE', '%' . $search . '%');
        }

        return $q->orderByRaw($coalesce);
    }
}
