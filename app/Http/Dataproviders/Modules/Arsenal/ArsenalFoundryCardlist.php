<?php
namespace App\Http\Dataproviders\Modules\Arsenal;

use App\Enum\GenericStringEnum;
use App\Http\Dataproviders\AbstractCardlist;
use App\Http\Dataproviders\Traits\HasPages;
use App\Models\Arsenal\Companion;
use App\Models\Arsenal\Component;
use App\Models\Arsenal\FoundryResult;
use App\Models\Arsenal\UserCompanion;
use App\Models\Arsenal\UserComponent;
use App\Models\Arsenal\UserWarframe;
use App\Models\Arsenal\UserWeapon;
use App\Models\Arsenal\Warframe;
use App\Models\Arsenal\Weapon;
use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Lati111\LaravelDataproviders\Traits\Dataprovider;
use Lati111\LaravelDataproviders\Traits\Paginatable;
use Symfony\Component\HttpFoundation\Response;

class ArsenalFoundryCardlist extends AbstractCardlist
{
    use Dataprovider, Paginatable, HasPages;

    /** { @inheritdoc } */
    public function data(Request $request): JsonResponse
    {
        $blueprints = $this->getData($request)->get();

        if ($blueprints->isEmpty()) {
            return $this->respond(Response::HTTP_OK, GenericStringEnum::DATA_RETRIEVED, collect());
        }

        $user = Auth::user();
        $componentsByBlueprint = $this->fetchComponents($user, $blueprints->pluck('blueprint_id')->toArray());

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

    /** { @inheritdoc } */
    protected function getContent(Request $request, bool $dataQuery = true): Builder
    {
        $user      = Auth::user();
        $search    = trim($request->get('search',    ''));
        $variant   = $request->get('variant',        'all');
        $itemType  = $request->get('item_type',      'all');
        $ownership = $request->get('ownership',      'all');
        $vault     = $request->get('vault',          'all');

        return FoundryResult::query()
            ->fromSub($this->buildInnerQuery($user, $search, $variant, $itemType, $ownership, $vault), 'foundry')
            ->orderByRaw('completion_pct DESC, name ASC');
    }

    private function buildInnerQuery(User $user, string $search, string $variant, string $itemType, string $ownership, string $vault)
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
            ->groupBy('c.blueprint_id', 'c.type', DB::raw('COALESCE(wf.name, wp.name, comp.name)'), DB::raw('COALESCE(wf.icon, wp.icon, comp.icon)'), DB::raw('COALESCE(wf.vaulted, wp.vaulted, 0)'))
            ->selectRaw("
                c.blueprint_id as blueprint_id,
                c.type as blueprint_type,
                COALESCE(wf.name, wp.name, comp.name) as name,
                COALESCE(wf.icon, wp.icon, comp.icon) as icon,
                COALESCE(wf.vaulted, wp.vaulted, 0) as vaulted,
                ROUND(SUM(LEAST(COALESCE(uc.amount, 0), c.amount)) / SUM(c.amount) * 100) as completion_pct,
                CASE
                    WHEN LOWER(c.type) = 'warframe'  THEN CASE WHEN EXISTS(SELECT 1 FROM " . UserWarframe::TABLE_NAME  . " WHERE id = c.blueprint_id AND owner_uuid = ?) THEN 1 ELSE 0 END
                    WHEN LOWER(c.type) = 'weapon'    THEN CASE WHEN EXISTS(SELECT 1 FROM " . UserWeapon::TABLE_NAME    . " WHERE id = c.blueprint_id AND owner_uuid = ?) THEN 1 ELSE 0 END
                    WHEN LOWER(c.type) = 'companion' THEN CASE WHEN EXISTS(SELECT 1 FROM " . UserCompanion::TABLE_NAME . " WHERE id = c.blueprint_id AND owner_uuid = ?) THEN 1 ELSE 0 END
                    ELSE 0
                END as already_owned
            ", [$user->uuid, $user->uuid, $user->uuid]);

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
            $q->where(function ($inner) {
                $inner->whereRaw("LOWER(c.type) = 'companion'")
                      ->orWhereRaw("LOWER(c.type) = 'weapon' AND LOWER(wp.type) = 'companion_weapon'");
            });
        } elseif ($itemType === 'archwing') {
            $q->whereRaw("LOWER(c.type) = 'weapon'")
              ->whereRaw("LOWER(wp.type) IN ('archgun', 'archmelee')");
        } elseif (in_array($itemType, ['primary', 'secondary', 'melee'])) {
            $q->whereRaw("LOWER(c.type) = 'weapon'")
              ->whereRaw('LOWER(wp.type) = ?', [$itemType]);
        }

        if ($ownership === 'owned') {
            $q->havingRaw('already_owned = 1');
        } elseif ($ownership === 'unowned') {
            $q->havingRaw('already_owned = 0');
        }

        if ($vault === 'vaulted') {
            $q->whereRaw('COALESCE(wf.vaulted, wp.vaulted, 0) = 1');
        } elseif ($vault === 'unvaulted') {
            $q->whereRaw('COALESCE(wf.vaulted, wp.vaulted, 0) = 0');
        }

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
