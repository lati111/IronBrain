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

class ArsenalArmoryCardlist extends AbstractCardlist
{
    private const DEFAULT_PER_PAGE = 9;

    /** { @inheritdoc } */
    public function data(Request $request): JsonResponse
    {
        $user = Auth::user();
        $search = trim($request->get('search', ''));
        $page = max(1, (int) $request->get('page', 1));
        $perPage = max(1, (int) $request->get('per_page', $request->get('perpage', self::DEFAULT_PER_PAGE)));
        [$category, $operator] = $this->parseCategoryFilter($request);

        $items = $this->buildUnionQuery($user, $search, $category, $operator)
            ->forPage($page, $perPage)
            ->get()
            ->map(function ($item) {
                $item->category_display = ucfirst($item->category);
                $item->item_type = $this->getItemType($item->category);
                $item->unowned = $item->owned ? 0 : 1;
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
        $search = trim($request->get('search', ''));
        $perPage = max(1, (int) $request->get('per_page', $request->get('perpage', self::DEFAULT_PER_PAGE)));
        [$category, $operator] = $this->parseCategoryFilter($request);

        $total = $this->buildUnionQuery($user, $search, $category, $operator)->count();

        return $this->respond(Response::HTTP_OK, GenericStringEnum::DATA_RETRIEVED, (int) ceil($total / $perPage));
    }

    public function filters(Request $request): JsonResponse
    {
        $filter = $request->get('filter');

        if ($filter === null) {
            return $this->respond(Response::HTTP_OK, GenericStringEnum::DATA_RETRIEVED, ['category']);
        }

        if ($filter === 'category') {
            return $this->respond(Response::HTTP_OK, GenericStringEnum::DATA_RETRIEVED, [
                'type' => 'select',
                'operators' => [
                    ['operator' => '=', 'text' => 'is'],
                    ['operator' => '!=', 'text' => 'is not'],
                ],
                'options' => ['warframe', 'primary', 'secondary', 'melee', 'companion', 'companion_weapon', 'archgun', 'archmelee'],
            ]);
        }

        return $this->respond(Response::HTTP_NOT_FOUND, 'Filter not found', null);
    }

    private function buildUnionQuery(User $user, string $search, ?string $category, string $operator): QueryBuilder
    {
        $warframes = DB::table(Warframe::TABLE_NAME . ' as w')
            ->select([
                DB::raw("'warframe' as category"),
                DB::raw('w.id as item_id'),
                DB::raw('w.name as name'),
                DB::raw('w.icon as icon'),
                DB::raw('w.prime as prime'),
                DB::raw('CASE WHEN uw.uuid IS NOT NULL THEN 1 ELSE 0 END as owned'),
            ])
            ->leftJoin(UserWarframe::TABLE_NAME . ' as uw', function ($join) use ($user) {
                $join->on('uw.id', '=', 'w.id')
                    ->where('uw.owner_uuid', '=', $user->uuid);
            });

        $weapons = DB::table(Weapon::TABLE_NAME . ' as wp')
            ->select([
                DB::raw('LOWER(wp.type) as category'),
                DB::raw('wp.id as item_id'),
                DB::raw('wp.name as name'),
                DB::raw('wp.icon as icon'),
                DB::raw('wp.prime as prime'),
                DB::raw('CASE WHEN uw.uuid IS NOT NULL THEN 1 ELSE 0 END as owned'),
            ])
            ->leftJoin(UserWeapon::TABLE_NAME . ' as uw', function ($join) use ($user) {
                $join->on('uw.id', '=', 'wp.id')
                    ->where('uw.owner_uuid', '=', $user->uuid);
            })
            ->whereNull('wp.exalted_id');

        $companions = DB::table(Companion::TABLE_NAME . ' as c')
            ->select([
                DB::raw("'companion' as category"),
                DB::raw('c.id as item_id'),
                DB::raw('c.name as name'),
                DB::raw('c.icon as icon'),
                DB::raw('c.prime as prime'),
                DB::raw('CASE WHEN uc.uuid IS NOT NULL THEN 1 ELSE 0 END as owned'),
            ])
            ->leftJoin(UserCompanion::TABLE_NAME . ' as uc', function ($join) use ($user) {
                $join->on('uc.id', '=', 'c.id')
                    ->where('uc.owner_uuid', '=', $user->uuid);
            });

        $union = $warframes->unionAll($weapons)->unionAll($companions);

        $outer = DB::query()->fromSub($union, 'armory');

        if ($search !== '') {
            $outer->where('name', 'LIKE', '%' . $search . '%');
        }

        if ($category !== null) {
            $outer->where('category', $operator, $category);
        }

        return $outer->orderByRaw('owned DESC, category ASC, name ASC');
    }

    private function parseCategoryFilter(Request $request): array
    {
        $direct = $request->get('category');
        if ($direct !== null) {
            return [$direct, '='];
        }

        $filtersJson = $request->get('filters', '[]');
        $filters = json_decode($filtersJson, true) ?? [];

        foreach ($filters as $filter) {
            if (isset($filter['filter']) && $filter['filter'] === 'category') {
                return [$filter['value'] ?? null, $filter['operator'] ?? '='];
            }
        }

        return [null, '='];
    }

    private function getItemType(string $category): string
    {
        return match ($category) {
            'warframe' => 'warframe',
            'companion' => 'companion',
            default => 'weapon',
        };
    }
}
