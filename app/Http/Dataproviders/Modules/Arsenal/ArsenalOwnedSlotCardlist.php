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
        $user = Auth::user();
        $slot = trim($request->get('slot', ''));
        $search = trim($request->get('search', ''));
        $page = max(1, (int) $request->get('page', 1));
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
        $user = Auth::user();
        $slot = trim($request->get('slot', ''));
        $search = trim($request->get('search', ''));
        $perPage = max(1, (int) $request->get('per_page', $request->get('perpage', self::DEFAULT_PER_PAGE)));

        $total = $this->buildQuery($user, $slot, $search)->count();
        return $this->respond(Response::HTTP_OK, GenericStringEnum::DATA_RETRIEVED, (int) ceil($total / $perPage));
    }

    private function buildQuery(User $user, string $slot, string $search): QueryBuilder
    {
        return match ($slot) {
            'warframe'  => $this->warframeQuery($user, $search),
            'companion' => $this->companionQuery($user, $search),
            default     => $this->weaponQuery($user, $slot, $search),
        };
    }

    private function warframeQuery(User $user, string $search): QueryBuilder
    {
        $q = DB::table(UserWarframe::TABLE_NAME . ' as uw')
            ->join(Warframe::TABLE_NAME . ' as w', 'uw.id', '=', 'w.id')
            ->where('uw.owner_uuid', $user->uuid)
            ->select([
                'uw.uuid as item_uuid',
                DB::raw('COALESCE(uw.name, w.name) as name'),
                'w.icon as icon',
            ]);

        if ($search !== '') {
            $q->where(DB::raw('COALESCE(uw.name, w.name)'), 'LIKE', '%' . $search . '%');
        }

        return $q->orderByRaw('COALESCE(uw.name, w.name)');
    }

    private function companionQuery(User $user, string $search): QueryBuilder
    {
        $q = DB::table(UserCompanion::TABLE_NAME . ' as uc')
            ->join(Companion::TABLE_NAME . ' as c', 'uc.id', '=', 'c.id')
            ->where('uc.owner_uuid', $user->uuid)
            ->select([
                'uc.uuid as item_uuid',
                DB::raw('COALESCE(uc.name, c.name) as name'),
                'c.icon as icon',
            ]);

        if ($search !== '') {
            $q->where(DB::raw('COALESCE(uc.name, c.name)'), 'LIKE', '%' . $search . '%');
        }

        return $q->orderByRaw('COALESCE(uc.name, c.name)');
    }

    private function weaponQuery(User $user, string $slot, string $search): QueryBuilder
    {
        $q = DB::table(UserWeapon::TABLE_NAME . ' as uw')
            ->join(Weapon::TABLE_NAME . ' as wp', 'uw.id', '=', 'wp.id')
            ->where('uw.owner_uuid', $user->uuid)
            ->whereRaw('LOWER(wp.type) = ?', [$slot])
            ->select([
                'uw.uuid as item_uuid',
                DB::raw('COALESCE(uw.name, wp.name) as name'),
                'wp.icon as icon',
            ]);

        if ($search !== '') {
            $q->where(DB::raw('COALESCE(uw.name, wp.name)'), 'LIKE', '%' . $search . '%');
        }

        return $q->orderByRaw('COALESCE(uw.name, wp.name)');
    }
}
