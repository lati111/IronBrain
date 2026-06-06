<?php
namespace App\Http\Dataproviders\Modules\Arsenal;

use App\Enum\GenericStringEnum;
use App\Http\Dataproviders\AbstractCardlist;
use App\Http\Dataproviders\Traits\HasPages;
use App\Models\Arsenal\Companion;
use App\Models\Arsenal\Loadout;
use App\Models\Arsenal\UserCompanion;
use App\Models\Arsenal\UserWarframe;
use App\Models\Arsenal\UserWeapon;
use App\Models\Arsenal\Warframe;
use App\Models\Arsenal\Weapon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Lati111\LaravelDataproviders\Traits\Dataprovider;
use Lati111\LaravelDataproviders\Traits\Paginatable;
use Lati111\LaravelDataproviders\Traits\Searchable;
use Symfony\Component\HttpFoundation\Response;

class ArsenalLoadoutCardlist extends AbstractCardlist
{
    use Dataprovider, Paginatable, HasPages, Searchable;

    /** { @inheritdoc } */
    public function data(Request $request): JsonResponse
    {
        $data = $this->getData($request)
            ->get()
            ->map(function ($loadout) {
                $loadout['has_warframe'] = $loadout->warframe_name !== null ? 1 : 0;
                if ($loadout->warframe_icon !== null) {
                    $loadout['warframe_icon'] = asset('img/' . $loadout->warframe_icon);
                }

                $loadout['has_primary'] = $loadout->primary_name !== null ? 1 : 0;
                if ($loadout->primary_icon !== null) {
                    $loadout['primary_icon'] = asset('img/' . $loadout->primary_icon);
                }

                $loadout['has_secondary'] = $loadout->secondary_name !== null ? 1 : 0;
                if ($loadout->secondary_icon !== null) {
                    $loadout['secondary_icon'] = asset('img/' . $loadout->secondary_icon);
                }

                $loadout['has_melee'] = $loadout->melee_name !== null ? 1 : 0;
                if ($loadout->melee_icon !== null) {
                    $loadout['melee_icon'] = asset('img/' . $loadout->melee_icon);
                }

                $loadout['has_companion'] = $loadout->companion_name !== null ? 1 : 0;
                if ($loadout->companion_icon !== null) {
                    $loadout['companion_icon'] = asset('img/' . $loadout->companion_icon);
                }

                $loadout['has_companion_weapon'] = $loadout->companion_weapon_name !== null ? 1 : 0;
                if ($loadout->companion_weapon_icon !== null) {
                    $loadout['companion_weapon_icon'] = asset('img/' . $loadout->companion_weapon_icon);
                }

                return $loadout;
            });

        return $this->respond(Response::HTTP_OK, GenericStringEnum::DATA_RETRIEVED, $data);
    }

    /** { @inheritdoc } */
    protected function getContent(Request $request, bool $dataQuery = true): Builder
    {
        $user = Auth::user();

        return Loadout::query()
            ->where(Loadout::TABLE_NAME . '.owner_uuid', $user->uuid)
            ->leftJoin(UserWarframe::TABLE_NAME . ' as uwf', Loadout::TABLE_NAME . '.warframe_uuid', '=', 'uwf.uuid')
            ->leftJoin(Warframe::TABLE_NAME . ' as wf', 'uwf.id', '=', 'wf.id')
            ->leftJoin(UserWeapon::TABLE_NAME . ' as upw', Loadout::TABLE_NAME . '.primary_uuid', '=', 'upw.uuid')
            ->leftJoin(Weapon::TABLE_NAME . ' as pw', 'upw.id', '=', 'pw.id')
            ->leftJoin(UserWeapon::TABLE_NAME . ' as usw', Loadout::TABLE_NAME . '.secondary_uuid', '=', 'usw.uuid')
            ->leftJoin(Weapon::TABLE_NAME . ' as sw', 'usw.id', '=', 'sw.id')
            ->leftJoin(UserWeapon::TABLE_NAME . ' as umw', Loadout::TABLE_NAME . '.melee_uuid', '=', 'umw.uuid')
            ->leftJoin(Weapon::TABLE_NAME . ' as mw', 'umw.id', '=', 'mw.id')
            ->leftJoin(UserCompanion::TABLE_NAME . ' as uc', Loadout::TABLE_NAME . '.companion_uuid', '=', 'uc.uuid')
            ->leftJoin(Companion::TABLE_NAME . ' as comp', 'uc.id', '=', 'comp.id')
            ->leftJoin(UserWeapon::TABLE_NAME . ' as ucw', Loadout::TABLE_NAME . '.companion_weapon_uuid', '=', 'ucw.uuid')
            ->leftJoin(Weapon::TABLE_NAME . ' as cw', 'ucw.id', '=', 'cw.id')
            ->orderBy(Loadout::TABLE_NAME . '.name')
            ->select([
                Loadout::TABLE_NAME . '.uuid',
                Loadout::TABLE_NAME . '.name as loadout_name',
                DB::raw('COALESCE(uwf.name, wf.name) as warframe_name'),
                'wf.icon as warframe_icon',
                DB::raw('COALESCE(upw.name, pw.name) as primary_name'),
                'pw.icon as primary_icon',
                DB::raw('COALESCE(usw.name, sw.name) as secondary_name'),
                'sw.icon as secondary_icon',
                DB::raw('COALESCE(umw.name, mw.name) as melee_name'),
                'mw.icon as melee_icon',
                DB::raw('COALESCE(uc.name, comp.name) as companion_name'),
                'comp.icon as companion_icon',
                DB::raw('COALESCE(ucw.name, cw.name) as companion_weapon_name'),
                'cw.icon as companion_weapon_icon',
            ]);
    }

    /** { @inheritdoc } */
    public function getSearchFields(): array
    {
        return [Loadout::TABLE_NAME . '.name'];
    }
}
