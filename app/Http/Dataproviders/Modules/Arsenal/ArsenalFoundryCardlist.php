<?php
namespace App\Http\Dataproviders\Modules\Arsenal;

use App\Enum\GenericStringEnum;
use App\Http\Dataproviders\AbstractCardlist;
use App\Models\Arsenal\Companion;
use App\Models\Arsenal\Component;
use App\Models\Arsenal\UserComponent;
use App\Models\Arsenal\Warframe;
use App\Models\Arsenal\Weapon;
use App\Models\Auth\User;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ArsenalFoundryCardlist extends AbstractCardlist
{
    private const int DEFAULT_PER_PAGE = 20;

    public function data(Request $request): JsonResponse
    {
        $user      = Auth::user();
        $search    = trim($request->get('search', ''));
        $variant   = $request->get('variant',   'all');
        $itemType  = $request->get('item_type', 'all');
        $ownership = $request->get('ownership', 'all');
        $page      = max(1, (int) $request->get('page', 1));
        $perPage   = max(1, (int) $request->get('per_page', $request->get('perpage', self::DEFAULT_PER_PAGE)));

        $blueprints = $this->buildBaseQuery($user, $search, $variant, $itemType, $ownership)
            ->forPage($page, $perPage)
            ->get();

        if ($blueprints->isEmpty()) {
            return $this->respond(Response::HTTP_OK, GenericStringEnum::DATA_RETRIEVED, collect());
        }

        $blueprintIds = $blueprints->pluck('blueprint_id')->toArray();
        $componentsByBlueprint = $this->fetchComponents($user, $blueprintIds);

        $result = $blueprints->map(function ($bp) use ($componentsByBlueprint) {
            if (!empty($bp->icon)) {
                $bp->icon = asset('img/' . $bp->icon);
            }

            $bp->components = ($componentsByBlueprint->get($bp->blueprint_id) ?? collect())
                ->map(function ($comp) {
                    if (!empty($comp->icon)) {
                        $comp->icon = asset('img/' . $comp->icon);
                    }
                    return $comp;
                })
                ->values()
                ->toArray();

            return $bp;
        });

        return $this->respond(Response::HTTP_OK, GenericStringEnum::DATA_RETRIEVED, $result);
    }

    public function count(Request $request): JsonResponse
    {
        $user      = Auth::user();
        $search    = trim($request->get('search', ''));
        $variant   = $request->get('variant',   'all');
        $itemType  = $request->get('item_type', 'all');
        $ownership = $request->get('ownership', 'all');
        $perPage   = max(1, (int) $request->get('per_page', $request->get('perpage', self::DEFAULT_PER_PAGE)));

        $subquery = $this->buildBaseQuery($user, $search, $variant, $itemType, $ownership);
        $total    = DB::select(
            'SELECT COUNT(*) as cnt FROM (' . $subquery->toSql() . ') as sub',
            $subquery->getBindings()
        )[0]->cnt;

        return $this->respond(Response::HTTP_OK, GenericStringEnum::DATA_RETRIEVED, (int) ceil($total / $perPage));
    }

    private function buildBaseQuery(User $user, string $search, string $variant = 'all', string $itemType = 'all', string $ownership = 'all'): QueryBuilder
    {
        $q = DB::table(Component::TABLE_NAME . ' as c')
            ->leftJoin(Warframe::TABLE_NAME . ' as wf', function ($join) {
                $join->on('c.blueprint_id', '=', 'wf.id')
                     ->whereRaw("LOWER(c.type) = 'warframe'");
            })
            ->leftJoin(Weapon::TABLE_NAME . ' as wp', function ($join) {
                $join->on('c.blueprint_id', '=', 'wp.id')
                     ->whereRaw("LOWER(c.type) = 'weapon'");
            })
            ->leftJoin(Companion::TABLE_NAME . ' as comp', function ($join) {
                $join->on('c.blueprint_id', '=', 'comp.id')
                     ->whereRaw("LOWER(c.type) = 'companion'");
            })
            ->leftJoin(UserComponent::TABLE_NAME . ' as uc', function ($join) use ($user) {
                $join->on('uc.id', '=', 'c.uuid')
                     ->where('uc.owner_uuid', '=', $user->uuid);
            })
            ->groupBy('c.blueprint_id', 'c.type', DB::raw('COALESCE(wf.name, wp.name, comp.name)'), DB::raw('COALESCE(wf.icon, wp.icon, comp.icon)'))
            ->selectRaw("
                c.blueprint_id as blueprint_id,
                c.type as blueprint_type,
                COALESCE(wf.name, wp.name, comp.name) as name,
                COALESCE(wf.icon, wp.icon, comp.icon) as icon,
                ROUND(SUM(LEAST(COALESCE(uc.amount, 0), c.amount)) / SUM(c.amount) * 100) as completion_pct
            ");

        if ($search !== '') {
            $q->where(function ($q) use ($search) {
                $q->whereRaw('COALESCE(wf.name, wp.name, comp.name) LIKE ?', ['%' . $search . '%'])
                  ->orWhereRaw(
                      'EXISTS (SELECT 1 FROM ' . Component::TABLE_NAME . ' c2 WHERE c2.blueprint_id = c.blueprint_id AND c2.name LIKE ?)',
                      ['%' . $search . '%']
                  );
            });
        }

        if ($variant === 'prime') {
            $q->whereRaw("COALESCE(wf.name, wp.name, comp.name) LIKE '%Prime%'");
        } elseif ($variant === 'non-prime') {
            $q->whereRaw("COALESCE(wf.name, wp.name, comp.name) NOT LIKE '%Prime%'");
        }

        if ($itemType === 'warframe') {
            $q->whereRaw("LOWER(c.type) = 'warframe'");
        } elseif ($itemType === 'companion') {
            $q->whereRaw("LOWER(c.type) = 'companion'");
        } elseif (in_array($itemType, ['primary', 'secondary', 'melee'])) {
            $q->whereRaw("LOWER(c.type) = 'weapon'")
              ->whereRaw('LOWER(wp.type) = ?', [$itemType]);
        }

        if ($ownership === 'owned') {
            $q->havingRaw('ROUND(SUM(LEAST(COALESCE(uc.amount, 0), c.amount)) / SUM(c.amount) * 100) >= 100');
        } elseif ($ownership === 'unowned') {
            $q->havingRaw('ROUND(SUM(LEAST(COALESCE(uc.amount, 0), c.amount)) / SUM(c.amount) * 100) < 100');
        }

        $q->orderByRaw('completion_pct DESC');
        $q->orderByRaw('COALESCE(wf.name, wp.name, comp.name) ASC');

        return $q;
    }

    private function fetchComponents(User $user, array $blueprintIds): Collection
    {
        return DB::table(Component::TABLE_NAME . ' as c')
            ->leftJoin(UserComponent::TABLE_NAME . ' as uc', function ($join) use ($user) {
                $join->on('uc.id', '=', 'c.uuid')
                     ->where('uc.owner_uuid', '=', $user->uuid);
            })
            ->whereIn('c.blueprint_id', $blueprintIds)
            ->select([
                'c.blueprint_id',
                'c.uuid as uuid',
                'c.name as name',
                'c.icon as icon',
                'c.amount as amount',
                DB::raw('COALESCE(uc.amount, 0) as obtained'),
            ])
            ->orderByRaw("CASE WHEN LOWER(c.name) LIKE '%blueprint%' THEN 0 ELSE 1 END ASC")
            ->orderBy('c.name')
            ->get()
            ->groupBy('blueprint_id');
    }
}
