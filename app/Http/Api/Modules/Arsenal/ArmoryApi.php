<?php
namespace App\Http\Api\Modules\Arsenal;

use App\Enum\ErrorEnum;
use App\Http\Api\AbstractApi;
use App\Models\Arsenal\Companion;
use App\Models\Arsenal\UserCompanion;
use App\Models\Arsenal\UserWarframe;
use App\Models\Arsenal\UserWeapon;
use App\Models\Arsenal\Warframe;
use App\Models\Arsenal\Weapon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class ArmoryApi extends AbstractApi
{
    /**
     * Add an item to the authenticated user's collection
     * @param Request $request The request parameters as passed by Laravel
     * @return JsonResponse The result in json format
     */
    public function addItem(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|string|max:255',
            'type' => ['required', 'string', Rule::in(['warframe', 'weapon', 'companion'])],
        ]);

        if ($validator->fails()) {
            return $this->respond(Response::HTTP_BAD_REQUEST, ErrorEnum::VALIDATION_FAIL, $validator->errors());
        }

        $user = Auth::user();
        $id = $request->get('id');
        $type = $request->get('type');

        return match ($type) {
            'warframe' => $this->addWarframe($user->uuid, $id),
            'weapon' => $this->addWeapon($user->uuid, $id),
            'companion' => $this->addCompanion($user->uuid, $id),
        };
    }

    private function addWarframe(string $ownerUuid, string $id): JsonResponse
    {
        if (!Warframe::where('id', $id)->exists()) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Warframe not found');
        }

        if (UserWarframe::where('id', $id)->where('owner_uuid', $ownerUuid)->exists()) {
            return $this->respond(Response::HTTP_ALREADY_REPORTED, 'Already in collection', true);
        }

        $item = new UserWarframe();
        $item->id = $id;
        $item->owner_uuid = $ownerUuid;
        $item->save();

        return $this->respond(Response::HTTP_CREATED, 'Added to collection', true);
    }

    private function addWeapon(string $ownerUuid, string $id): JsonResponse
    {
        if (!Weapon::where('id', $id)->exists()) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Weapon not found');
        }

        if (UserWeapon::where('id', $id)->where('owner_uuid', $ownerUuid)->exists()) {
            return $this->respond(Response::HTTP_ALREADY_REPORTED, 'Already in collection', true);
        }

        $item = new UserWeapon();
        $item->id = $id;
        $item->owner_uuid = $ownerUuid;
        $item->save();

        return $this->respond(Response::HTTP_CREATED, 'Added to collection', true);
    }

    private function addCompanion(string $ownerUuid, string $id): JsonResponse
    {
        if (!Companion::where('id', $id)->exists()) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Companion not found');
        }

        if (UserCompanion::where('id', $id)->where('owner_uuid', $ownerUuid)->exists()) {
            return $this->respond(Response::HTTP_ALREADY_REPORTED, 'Already in collection', true);
        }

        $item = new UserCompanion();
        $item->id = $id;
        $item->owner_uuid = $ownerUuid;
        $item->save();

        return $this->respond(Response::HTTP_CREATED, 'Added to collection', true);
    }
}
