<?php
namespace App\Http\Api\Modules\Arsenal;

use App\Enum\ErrorEnum;
use App\Http\Api\AbstractApi;
use App\Models\Arsenal\Companion;
use App\Models\Arsenal\Loadout;
use App\Models\Arsenal\UserCompanion;
use App\Models\Arsenal\UserWarframe;
use App\Models\Arsenal\UserWeapon;
use App\Models\Arsenal\Warframe;
use App\Models\Arsenal\Weapon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class LoadoutApi extends AbstractApi
{
    public function createLoadout(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'warframe_uuid' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->respond(Response::HTTP_BAD_REQUEST, ErrorEnum::VALIDATION_FAIL, $validator->errors());
        }

        $user = Auth::user();

        $warframe = UserWarframe::where('uuid', $request->get('warframe_uuid'))
            ->where('owner_uuid', $user->uuid)
            ->first();

        if (!$warframe) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Warframe not found in collection');
        }

        $count = Loadout::where('owner_uuid', $user->uuid)->count();

        $loadout = new Loadout();
        $loadout->owner_uuid    = $user->uuid;
        $loadout->name          = 'Loadout ' . ($count + 1);
        $loadout->warframe_uuid = $request->get('warframe_uuid');
        $loadout->save();

        return $this->respond(Response::HTTP_CREATED, 'Loadout created', $loadout->uuid);
    }

    public function assignSlot(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'loadout_uuid' => 'required|string|max:255',
            'slot'         => ['required', 'string', Rule::in(['warframe', 'primary', 'secondary', 'melee', 'companion', 'companion_weapon'])],
            'item_uuid'    => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->respond(Response::HTTP_BAD_REQUEST, ErrorEnum::VALIDATION_FAIL, $validator->errors());
        }

        $user = Auth::user();
        $loadout = Loadout::where('uuid', $request->get('loadout_uuid'))
            ->where('owner_uuid', $user->uuid)
            ->first();

        if (!$loadout) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Loadout not found');
        }

        $slot     = $request->get('slot');
        $itemUuid = $request->get('item_uuid');

        [$valid, $itemData, $error] = $this->validateAndGetSlotItem($user->uuid, $slot, $itemUuid);
        if (!$valid) {
            return $this->respond(Response::HTTP_NOT_FOUND, $error);
        }

        $loadout->{$slot . '_uuid'} = $itemUuid;
        $loadout->save();

        return $this->respond(Response::HTTP_OK, 'Slot assigned', $itemData);
    }

    public function clearSlot(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'loadout_uuid' => 'required|string|max:255',
            'slot'         => ['required', 'string', Rule::in(['warframe', 'primary', 'secondary', 'melee', 'companion', 'companion_weapon'])],
        ]);

        if ($validator->fails()) {
            return $this->respond(Response::HTTP_BAD_REQUEST, ErrorEnum::VALIDATION_FAIL, $validator->errors());
        }

        $user = Auth::user();

        if ($request->get('slot') === 'warframe') {
            return $this->respond(Response::HTTP_BAD_REQUEST, 'Warframe slot cannot be cleared');
        }

        $loadout = Loadout::where('uuid', $request->get('loadout_uuid'))
            ->where('owner_uuid', $user->uuid)
            ->first();

        if (!$loadout) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Loadout not found');
        }

        $loadout->{$request->get('slot') . '_uuid'} = null;
        $loadout->save();

        return $this->respond(Response::HTTP_OK, 'Slot cleared', null);
    }

    public function renameLoadout(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'loadout_uuid' => 'required|string|max:255',
            'name'         => 'required|string|min:1|max:32',
        ]);

        if ($validator->fails()) {
            return $this->respond(Response::HTTP_BAD_REQUEST, ErrorEnum::VALIDATION_FAIL, $validator->errors());
        }

        $user = Auth::user();
        $loadout = Loadout::where('uuid', $request->get('loadout_uuid'))
            ->where('owner_uuid', $user->uuid)
            ->first();

        if (!$loadout) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Loadout not found');
        }

        $loadout->name = $request->get('name');
        $loadout->save();

        return $this->respond(Response::HTTP_OK, 'Loadout renamed', true);
    }

    // ─── Slot item helpers ────────────────────────────────────────────────────

    private function validateAndGetSlotItem(string $ownerUuid, string $slot, string $itemUuid): array
    {
        return match ($slot) {
            'warframe'  => $this->getWarframeSlotItem($ownerUuid, $itemUuid),
            'companion' => $this->getCompanionSlotItem($ownerUuid, $itemUuid),
            default     => $this->getWeaponSlotItem($ownerUuid, $slot, $itemUuid),
        };
    }

    private function getWarframeSlotItem(string $ownerUuid, string $itemUuid): array
    {
        $item = DB::table(UserWarframe::TABLE_NAME . ' as uw')
            ->join(Warframe::TABLE_NAME . ' as w', 'uw.id', '=', 'w.id')
            ->where('uw.uuid', $itemUuid)
            ->where('uw.owner_uuid', $ownerUuid)
            ->select([DB::raw('COALESCE(uw.name, w.name) as name'), 'w.icon'])
            ->first();

        if (!$item) {
            return [false, null, 'Warframe not found in collection'];
        }

        return [true, [
            'name' => $item->name,
            'icon' => $item->icon ? asset('img/' . $item->icon) : null,
        ], null];
    }

    private function getCompanionSlotItem(string $ownerUuid, string $itemUuid): array
    {
        $item = DB::table(UserCompanion::TABLE_NAME . ' as uc')
            ->join(Companion::TABLE_NAME . ' as c', 'uc.id', '=', 'c.id')
            ->where('uc.uuid', $itemUuid)
            ->where('uc.owner_uuid', $ownerUuid)
            ->select([DB::raw('COALESCE(uc.name, c.name) as name'), 'c.icon'])
            ->first();

        if (!$item) {
            return [false, null, 'Companion not found in collection'];
        }

        return [true, [
            'name' => $item->name,
            'icon' => $item->icon ? asset('img/' . $item->icon) : null,
        ], null];
    }

    private function getWeaponSlotItem(string $ownerUuid, string $slot, string $itemUuid): array
    {
        $item = DB::table(UserWeapon::TABLE_NAME . ' as uw')
            ->join(Weapon::TABLE_NAME . ' as wp', 'uw.id', '=', 'wp.id')
            ->where('uw.uuid', $itemUuid)
            ->where('uw.owner_uuid', $ownerUuid)
            ->whereRaw('LOWER(wp.type) = ?', [$slot])
            ->select([DB::raw('COALESCE(uw.name, wp.name) as name'), 'wp.icon'])
            ->first();

        if (!$item) {
            return [false, null, 'Weapon not found in collection'];
        }

        return [true, [
            'name' => $item->name,
            'icon' => $item->icon ? asset('img/' . $item->icon) : null,
        ], null];
    }
}
