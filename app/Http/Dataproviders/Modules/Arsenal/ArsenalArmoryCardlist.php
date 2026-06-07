<?php
namespace App\Http\Dataproviders\Modules\Arsenal;

use App\Enum\GenericStringEnum;
use App\Http\Dataproviders\AbstractCardlist;
use App\Models\Arsenal\ArmoryResult;
use App\Models\Arsenal\Companion;
use App\Models\Arsenal\UserCompanion;
use App\Models\Arsenal\UserWarframe;
use App\Models\Arsenal\UserWeapon;
use App\Models\Arsenal\Warframe;
use App\Models\Arsenal\Weapon;
use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Lati111\LaravelDataproviders\Traits\Dataprovider;
use Lati111\LaravelDataproviders\Traits\Paginatable;
use Lati111\LaravelDataproviders\Traits\Searchable;
use Symfony\Component\HttpFoundation\Response;

class ArsenalArmoryCardlist extends AbstractCardlist
{
    use Dataprovider, Paginatable, Searchable;

    public function __construct()
    {
        $this->setDefaultPerPage(9);
    }

    public function data(Request $request): JsonResponse
    {
        $items = $this->getData($request)
            ->get()
            ->map(function ($item) {
                $item->category_display = ucfirst($item->category);
                $item->item_type = $this->getItemType($item->category);
                $item->unowned = $item->owned ? 0 : 1;
                $item->school_abbr = $item->school ? strtoupper(substr($item->school, 0, 1)) : '';
                if ($item->icon !== null) {
                    $item->icon = asset('img/' . $item->icon);
                }
                return $item;
            });

        return $this->respond(Response::HTTP_OK, GenericStringEnum::DATA_RETRIEVED, $items);
    }

    public function count(Request $request): JsonResponse
    {
        return $this->respond(Response::HTTP_OK, GenericStringEnum::DATA_RETRIEVED, $this->getPages($request));
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

    protected function getContent(Request $request, bool $dataQuery = true): Builder
    {
        $user = Auth::user();
        $q = ArmoryResult::query()->fromSub($this->buildUnionQuery($user), 'armory');

        $category = $request->get('category');
        $operator = '=';

        if ($category === null) {
            $filtersJson = $request->get('filters', '[]');
            foreach (json_decode($filtersJson, true) ?? [] as $filter) {
                if (isset($filter['filter']) && $filter['filter'] === 'category') {
                    $category = $filter['value'] ?? null;
                    $operator = $filter['operator'] ?? '=';
                    break;
                }
            }
        }

        if ($category !== null) {
            $q->where('category', $operator, $category);
        }

        $variant = $request->get('variant');
        if ($variant === 'prime') {
            $q->where('prime', 1);
        } elseif ($variant === 'non-prime') {
            $q->where('prime', 0);
        }

        $ownership = $request->get('ownership');
        if ($ownership === 'owned') {
            $q->where('owned', 1);
        } elseif ($ownership === 'unowned') {
            $q->where('owned', 0);
        }

        return $q->orderByRaw('owned DESC, category ASC, name ASC');
    }

    public function getSearchFields(): array
    {
        return ['name'];
    }

    private function buildUnionQuery(User $user)
    {
        $ownedWarframes = DB::table(UserWarframe::TABLE_NAME . ' as uw')
            ->join(Warframe::TABLE_NAME . ' as w', 'uw.id', '=', 'w.id')
            ->where('uw.owner_uuid', $user->uuid)
            ->select([
                DB::raw("'warframe' as category"),
                DB::raw('w.id as item_id'),
                DB::raw('COALESCE(uw.name, w.name) as name'),
                DB::raw('w.icon as icon'),
                DB::raw('w.prime as prime'),
                DB::raw('1 as owned'),
                DB::raw('uw.uuid as user_uuid'),
                DB::raw('uw.forma as forma'),
                DB::raw('uw.potato as potato'),
                DB::raw('uw.built as built'),
                DB::raw('0 as riven'),
                DB::raw('uw.school as school'),
            ]);

        $unownedWarframes = DB::table(Warframe::TABLE_NAME . ' as w')
            ->whereNotIn('w.id', fn($q) => $q->from(UserWarframe::TABLE_NAME)->select('id')->where('owner_uuid', $user->uuid))
            ->select([
                DB::raw("'warframe' as category"),
                DB::raw('w.id as item_id'),
                DB::raw('w.name as name'),
                DB::raw('w.icon as icon'),
                DB::raw('w.prime as prime'),
                DB::raw('0 as owned'),
                DB::raw('NULL as user_uuid'),
                DB::raw('0 as forma'),
                DB::raw('0 as potato'),
                DB::raw('0 as built'),
                DB::raw('0 as riven'),
                DB::raw('NULL as school'),
            ]);

        $ownedWeapons = DB::table(UserWeapon::TABLE_NAME . ' as uw')
            ->join(Weapon::TABLE_NAME . ' as wp', 'uw.id', '=', 'wp.id')
            ->where('uw.owner_uuid', $user->uuid)
            ->whereNull('wp.exalted_id')
            ->select([
                DB::raw('LOWER(wp.type) as category'),
                DB::raw('wp.id as item_id'),
                DB::raw('COALESCE(uw.name, wp.name) as name'),
                DB::raw('wp.icon as icon'),
                DB::raw('wp.prime as prime'),
                DB::raw('1 as owned'),
                DB::raw('uw.uuid as user_uuid'),
                DB::raw('uw.forma as forma'),
                DB::raw('uw.potato as potato'),
                DB::raw('uw.built as built'),
                DB::raw('uw.riven as riven'),
                DB::raw('uw.school as school'),
            ]);

        $unownedWeapons = DB::table(Weapon::TABLE_NAME . ' as wp')
            ->whereNotIn('wp.id', fn($q) => $q->from(UserWeapon::TABLE_NAME)->select('id')->where('owner_uuid', $user->uuid))
            ->whereNull('wp.exalted_id')
            ->select([
                DB::raw('LOWER(wp.type) as category'),
                DB::raw('wp.id as item_id'),
                DB::raw('wp.name as name'),
                DB::raw('wp.icon as icon'),
                DB::raw('wp.prime as prime'),
                DB::raw('0 as owned'),
                DB::raw('NULL as user_uuid'),
                DB::raw('0 as forma'),
                DB::raw('0 as potato'),
                DB::raw('0 as built'),
                DB::raw('0 as riven'),
                DB::raw('NULL as school'),
            ]);

        $ownedCompanions = DB::table(UserCompanion::TABLE_NAME . ' as uc')
            ->join(Companion::TABLE_NAME . ' as c', 'uc.id', '=', 'c.id')
            ->where('uc.owner_uuid', $user->uuid)
            ->select([
                DB::raw("'companion' as category"),
                DB::raw('c.id as item_id'),
                DB::raw('COALESCE(uc.name, c.name) as name'),
                DB::raw('c.icon as icon'),
                DB::raw('c.prime as prime'),
                DB::raw('1 as owned'),
                DB::raw('uc.uuid as user_uuid'),
                DB::raw('uc.forma as forma'),
                DB::raw('uc.potato as potato'),
                DB::raw('uc.built as built'),
                DB::raw('0 as riven'),
                DB::raw('uc.school as school'),
            ]);

        $unownedCompanions = DB::table(Companion::TABLE_NAME . ' as c')
            ->whereNotIn('c.id', fn($q) => $q->from(UserCompanion::TABLE_NAME)->select('id')->where('owner_uuid', $user->uuid))
            ->select([
                DB::raw("'companion' as category"),
                DB::raw('c.id as item_id'),
                DB::raw('c.name as name'),
                DB::raw('c.icon as icon'),
                DB::raw('c.prime as prime'),
                DB::raw('0 as owned'),
                DB::raw('NULL as user_uuid'),
                DB::raw('0 as forma'),
                DB::raw('0 as potato'),
                DB::raw('0 as built'),
                DB::raw('0 as riven'),
                DB::raw('NULL as school'),
            ]);

        return $ownedWarframes
            ->unionAll($unownedWarframes)
            ->unionAll($ownedWeapons)
            ->unionAll($unownedWeapons)
            ->unionAll($ownedCompanions)
            ->unionAll($unownedCompanions);
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
