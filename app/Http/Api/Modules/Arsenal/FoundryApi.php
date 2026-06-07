<?php
namespace App\Http\Api\Modules\Arsenal;

use App\Enum\ErrorEnum;
use App\Http\Api\AbstractApi;
use App\Models\Arsenal\Companion;
use App\Models\Arsenal\Component;
use App\Models\Arsenal\UserCompanion;
use App\Models\Arsenal\UserComponent;
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

class FoundryApi extends AbstractApi
{
    public function setComponentAmount(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'component_uuid' => 'required|string|max:255',
            'amount'         => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return $this->respond(Response::HTTP_BAD_REQUEST, ErrorEnum::VALIDATION_FAIL, $validator->errors());
        }

        $user          = Auth::user();
        $componentUuid = $request->get('component_uuid');
        $amount        = (int) $request->get('amount');

        $component = Component::where('uuid', $componentUuid)->first();

        if (!$component) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Component not found');
        }

        if ($amount === 0) {
            UserComponent::where('id', $componentUuid)
                ->where('owner_uuid', $user->uuid)
                ->delete();
        } else {
            $userComponent = UserComponent::where('id', $componentUuid)
                ->where('owner_uuid', $user->uuid)
                ->first();

            if ($userComponent === null) {
                $userComponent             = new UserComponent();
                $userComponent->id         = $componentUuid;
                $userComponent->owner_uuid = $user->uuid;
            }

            $userComponent->amount = min($amount, $component->amount);
            $userComponent->save();
        }

        return $this->respond(Response::HTTP_OK, 'Component updated', null);
    }

    public function craftBlueprint(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'blueprint_id' => 'required|string|max:255',
            'type'         => ['required', 'string', Rule::in(['warframe', 'weapon', 'companion'])],
        ]);

        if ($validator->fails()) {
            return $this->respond(Response::HTTP_BAD_REQUEST, ErrorEnum::VALIDATION_FAIL, $validator->errors());
        }

        $user        = Auth::user();
        $blueprintId = $request->get('blueprint_id');
        $type        = $request->get('type');

        $components = Component::where('blueprint_id', $blueprintId)->get();

        if ($components->isEmpty()) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Blueprint not found');
        }

        $componentUuids = $components->pluck('uuid');
        $userComponents = UserComponent::where('owner_uuid', $user->uuid)
            ->whereIn('id', $componentUuids)
            ->get()
            ->keyBy('id');

        foreach ($components as $component) {
            $obtained = (int) ($userComponents->get($component->uuid)?->amount ?? 0);
            if ($obtained < $component->amount) {
                return $this->respond(Response::HTTP_FORBIDDEN, 'Blueprint is not complete');
            }
        }

        UserComponent::where('owner_uuid', $user->uuid)
            ->whereIn('id', $componentUuids)
            ->delete();

        return match ($type) {
            'warframe'  => $this->grantWarframe($user->uuid, $blueprintId),
            'weapon'    => $this->grantWeapon($user->uuid, $blueprintId),
            'companion' => $this->grantCompanion($user->uuid, $blueprintId),
        };
    }

    private function grantWarframe(string $ownerUuid, string $id): JsonResponse
    {
        if (!Warframe::where('id', $id)->exists()) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Warframe not found');
        }
        $item             = new UserWarframe();
        $item->id         = $id;
        $item->owner_uuid = $ownerUuid;
        $item->save();
        return $this->respond(Response::HTTP_OK, 'Blueprint crafted', null);
    }

    private function grantWeapon(string $ownerUuid, string $id): JsonResponse
    {
        if (!Weapon::where('id', $id)->exists()) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Weapon not found');
        }
        $item             = new UserWeapon();
        $item->id         = $id;
        $item->owner_uuid = $ownerUuid;
        $item->save();
        return $this->respond(Response::HTTP_OK, 'Blueprint crafted', null);
    }

    private function grantCompanion(string $ownerUuid, string $id): JsonResponse
    {
        if (!Companion::where('id', $id)->exists()) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Companion not found');
        }
        $item             = new UserCompanion();
        $item->id         = $id;
        $item->owner_uuid = $ownerUuid;
        $item->save();
        return $this->respond(Response::HTTP_OK, 'Blueprint crafted', null);
    }
}
