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
            'warframe'  => $this->fetchSlotItem(UserWarframe::TABLE_NAME,  'uw', Warframe::TABLE_NAME,   'w',   $ownerUuid, $itemUuid, 'Warframe not found in collection'),
            'companion' => $this->fetchSlotItem(UserCompanion::TABLE_NAME, 'uc', Companion::TABLE_NAME,  'c',   $ownerUuid, $itemUuid, 'Companion not found in collection'),
            default     => $this->fetchSlotItem(UserWeapon::TABLE_NAME,    'uw', Weapon::TABLE_NAME,     'wp',  $ownerUuid, $itemUuid, 'Weapon not found in collection', ['LOWER(wp.type) = ?', [$slot]]),
        };
    }

    private function fetchSlotItem(
        string $userTable, string $userAlias,
        string $baseTable, string $baseAlias,
        string $ownerUuid, string $itemUuid, string $notFoundMsg,
        ?array $extraWhere = null
    ): array {
        $q = DB::table("$userTable as $userAlias")
            ->join("$baseTable as $baseAlias", "$userAlias.id", '=', "$baseAlias.id")
            ->where("$userAlias.uuid", $itemUuid)
            ->where("$userAlias.owner_uuid", $ownerUuid)
            ->select([DB::raw("COALESCE($userAlias.name, $baseAlias.name) as name"), "$baseAlias.icon"]);

        if ($extraWhere !== null) {
            $q->whereRaw($extraWhere[0], $extraWhere[1]);
        }

        $item = $q->first();
        if (!$item) {
            return [false, null, $notFoundMsg];
        }

        return [true, [
            'name' => $item->name,
            'icon' => $item->icon ? asset('img/' . $item->icon) : null,
        ], null];
    }
}
