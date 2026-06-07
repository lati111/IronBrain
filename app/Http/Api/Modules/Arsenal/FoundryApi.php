<?php
namespace App\Http\Api\Modules\Arsenal;

use App\Enum\ErrorEnum;
use App\Http\Api\AbstractApi;
use App\Models\Arsenal\Component;
use App\Models\Arsenal\UserComponent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
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
}
