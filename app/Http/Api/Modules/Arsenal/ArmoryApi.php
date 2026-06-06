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
    public function addItem(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id'   => 'required|string|max:255',
            'type' => ['required', 'string', Rule::in(['warframe', 'weapon', 'companion'])],
        ]);

        if ($validator->fails()) {
            return $this->respond(Response::HTTP_BAD_REQUEST, ErrorEnum::VALIDATION_FAIL, $validator->errors());
        }

        $user = Auth::user();

        return match ($request->get('type')) {
            'warframe'  => $this->addWarframe($user->uuid, $request->get('id')),
            'weapon'    => $this->addWeapon($user->uuid, $request->get('id')),
            'companion' => $this->addCompanion($user->uuid, $request->get('id')),
        };
    }

    public function getItem(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'uuid' => 'required|string|max:255',
            'type' => ['required', 'string', Rule::in(['warframe', 'weapon', 'companion'])],
        ]);

        if ($validator->fails()) {
            return $this->respond(Response::HTTP_BAD_REQUEST, ErrorEnum::VALIDATION_FAIL, $validator->errors());
        }

        $user = Auth::user();

        return match ($request->get('type')) {
            'warframe'  => $this->fetchWarframe($user->uuid, $request->get('uuid')),
            'weapon'    => $this->fetchWeapon($user->uuid, $request->get('uuid')),
            'companion' => $this->fetchCompanion($user->uuid, $request->get('uuid')),
        };
    }

    public function updateItem(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'uuid'           => 'required|string|max:255',
            'type'           => ['required', 'string', Rule::in(['warframe', 'weapon', 'companion'])],
            'forma'          => 'nullable|integer|min:0|max:10',
            'potato'         => 'nullable|in:0,1',
            'built'          => 'nullable|in:0,1',
            'exilus'         => 'nullable|in:0,1',
            'fashioned'      => 'nullable|in:0,1',
            'riven'          => 'nullable|in:0,1',
            'school'         => ['nullable', 'string', Rule::in(['', 'madurai', 'vazarin', 'naramon', 'unairu', 'zenurik'])],
            'name'           => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->respond(Response::HTTP_BAD_REQUEST, ErrorEnum::VALIDATION_FAIL, $validator->errors());
        }

        $user = Auth::user();

        return match ($request->get('type')) {
            'warframe'  => $this->saveWarframe($user->uuid, $request),
            'weapon'    => $this->saveWeapon($user->uuid, $request),
            'companion' => $this->saveCompanion($user->uuid, $request),
        };
    }

    public function removeItem(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'uuid' => 'required|string|max:255',
            'type' => ['required', 'string', Rule::in(['warframe', 'weapon', 'companion'])],
        ]);

        if ($validator->fails()) {
            return $this->respond(Response::HTTP_BAD_REQUEST, ErrorEnum::VALIDATION_FAIL, $validator->errors());
        }

        $user = Auth::user();

        return match ($request->get('type')) {
            'warframe'  => $this->deleteWarframe($user->uuid, $request->get('uuid')),
            'weapon'    => $this->deleteWeapon($user->uuid, $request->get('uuid')),
            'companion' => $this->deleteCompanion($user->uuid, $request->get('uuid')),
        };
    }

    // ─── Add ─────────────────────────────────────────────────────────────────

    private function addWarframe(string $ownerUuid, string $id): JsonResponse
    {
        if (!Warframe::where('id', $id)->exists()) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Warframe not found');
        }

        $existing = UserWarframe::where('id', $id)->where('owner_uuid', $ownerUuid)->first();
        if ($existing) {
            return $this->respond(Response::HTTP_ALREADY_REPORTED, 'Already in collection', $existing->uuid);
        }

        $item = new UserWarframe();
        $item->id = $id;
        $item->owner_uuid = $ownerUuid;
        $item->save();

        return $this->respond(Response::HTTP_CREATED, 'Added to collection', $item->uuid);
    }

    private function addWeapon(string $ownerUuid, string $id): JsonResponse
    {
        if (!Weapon::where('id', $id)->exists()) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Weapon not found');
        }

        $existing = UserWeapon::where('id', $id)->where('owner_uuid', $ownerUuid)->first();
        if ($existing) {
            return $this->respond(Response::HTTP_ALREADY_REPORTED, 'Already in collection', $existing->uuid);
        }

        $item = new UserWeapon();
        $item->id = $id;
        $item->owner_uuid = $ownerUuid;
        $item->save();

        return $this->respond(Response::HTTP_CREATED, 'Added to collection', $item->uuid);
    }

    private function addCompanion(string $ownerUuid, string $id): JsonResponse
    {
        if (!Companion::where('id', $id)->exists()) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Companion not found');
        }

        $existing = UserCompanion::where('id', $id)->where('owner_uuid', $ownerUuid)->first();
        if ($existing) {
            return $this->respond(Response::HTTP_ALREADY_REPORTED, 'Already in collection', $existing->uuid);
        }

        $item = new UserCompanion();
        $item->id = $id;
        $item->owner_uuid = $ownerUuid;
        $item->save();

        return $this->respond(Response::HTTP_CREATED, 'Added to collection', $item->uuid);
    }

    // ─── Fetch ────────────────────────────────────────────────────────────────

    private function fetchWarframe(string $ownerUuid, string $uuid): JsonResponse
    {
        $item = UserWarframe::where('uuid', $uuid)->where('owner_uuid', $ownerUuid)->first();
        if (!$item) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Item not found');
        }

        return $this->respond(Response::HTTP_OK, 'Data retrieved', [
            'name'      => $item->name,
            'base_name' => $item->getWarframe()?->name,
            'forma'     => $item->forma,
            'potato'    => (bool) $item->potato,
            'built'     => (bool) $item->built,
            'exilus'    => (bool) $item->exilus,
            'fashioned' => (bool) $item->fashioned,
            'school'    => $item->school,
        ]);
    }

    private function fetchWeapon(string $ownerUuid, string $uuid): JsonResponse
    {
        $item = UserWeapon::where('uuid', $uuid)->where('owner_uuid', $ownerUuid)->first();
        if (!$item) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Item not found');
        }

        return $this->respond(Response::HTTP_OK, 'Data retrieved', [
            'name'      => $item->name,
            'base_name' => $item->getWeapon()?->name,
            'forma'     => $item->forma,
            'potato'    => (bool) $item->potato,
            'built'     => (bool) $item->built,
            'exilus'    => (bool) $item->exilus,
            'riven'     => (bool) $item->riven,
        ]);
    }

    private function fetchCompanion(string $ownerUuid, string $uuid): JsonResponse
    {
        $item = UserCompanion::where('uuid', $uuid)->where('owner_uuid', $ownerUuid)->first();
        if (!$item) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Item not found');
        }

        return $this->respond(Response::HTTP_OK, 'Data retrieved', [
            'name'      => $item->name,
            'base_name' => $item->getCompanion()?->name,
            'forma'     => $item->forma,
            'potato'    => (bool) $item->potato,
            'built'     => (bool) $item->built,
            'fashioned' => (bool) $item->fashioned,
        ]);
    }

    // ─── Save ─────────────────────────────────────────────────────────────────

    private function saveWarframe(string $ownerUuid, Request $request): JsonResponse
    {
        $item = UserWarframe::where('uuid', $request->get('uuid'))->where('owner_uuid', $ownerUuid)->first();
        if (!$item) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Item not found');
        }

        $item->name      = $request->get('name') ?: null;
        $item->forma     = (int) $request->get('forma', 0);
        $item->potato    = (bool) $request->get('potato', false);
        $item->built     = (bool) $request->get('built', false);
        $item->exilus    = (bool) $request->get('exilus', false);
        $item->fashioned = (bool) $request->get('fashioned', false);
        $item->school    = $request->get('school') ?: null;
        $item->save();

        return $this->respond(Response::HTTP_OK, 'Saved', true);
    }

    private function saveWeapon(string $ownerUuid, Request $request): JsonResponse
    {
        $item = UserWeapon::where('uuid', $request->get('uuid'))->where('owner_uuid', $ownerUuid)->first();
        if (!$item) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Item not found');
        }

        $item->name   = $request->get('name') ?: null;
        $item->forma  = (int) $request->get('forma', 0);
        $item->potato = (bool) $request->get('potato', false);
        $item->built  = (bool) $request->get('built', false);
        $item->exilus = (bool) $request->get('exilus', false);
        $item->riven  = (bool) $request->get('riven', false);
        $item->save();

        return $this->respond(Response::HTTP_OK, 'Saved', true);
    }

    private function saveCompanion(string $ownerUuid, Request $request): JsonResponse
    {
        $item = UserCompanion::where('uuid', $request->get('uuid'))->where('owner_uuid', $ownerUuid)->first();
        if (!$item) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Item not found');
        }

        $item->name      = $request->get('name') ?: null;
        $item->forma     = (int) $request->get('forma', 0);
        $item->potato    = (bool) $request->get('potato', false);
        $item->built     = (bool) $request->get('built', false);
        $item->fashioned = (bool) $request->get('fashioned', false);
        $item->save();

        return $this->respond(Response::HTTP_OK, 'Saved', true);
    }

    // ─── Duplicate ────────────────────────────────────────────────────────────

    public function duplicateItem(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'uuid' => 'required|string|max:255',
            'type' => ['required', 'string', Rule::in(['warframe', 'weapon', 'companion'])],
        ]);

        if ($validator->fails()) {
            return $this->respond(Response::HTTP_BAD_REQUEST, ErrorEnum::VALIDATION_FAIL, $validator->errors());
        }

        $user = Auth::user();

        return match ($request->get('type')) {
            'warframe'  => $this->duplicateWarframe($user->uuid, $request->get('uuid')),
            'weapon'    => $this->duplicateWeapon($user->uuid, $request->get('uuid')),
            'companion' => $this->duplicateCompanion($user->uuid, $request->get('uuid')),
        };
    }

    private function duplicateWarframe(string $ownerUuid, string $uuid): JsonResponse
    {
        $source = UserWarframe::where('uuid', $uuid)->where('owner_uuid', $ownerUuid)->first();
        if (!$source) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Item not found');
        }

        $copy = new UserWarframe();
        $copy->id = $source->id;
        $copy->owner_uuid = $ownerUuid;
        $copy->save();

        return $this->respond(Response::HTTP_CREATED, 'Duplicate added', $copy->uuid);
    }

    private function duplicateWeapon(string $ownerUuid, string $uuid): JsonResponse
    {
        $source = UserWeapon::where('uuid', $uuid)->where('owner_uuid', $ownerUuid)->first();
        if (!$source) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Item not found');
        }

        $copy = new UserWeapon();
        $copy->id = $source->id;
        $copy->owner_uuid = $ownerUuid;
        $copy->save();

        return $this->respond(Response::HTTP_CREATED, 'Duplicate added', $copy->uuid);
    }

    private function duplicateCompanion(string $ownerUuid, string $uuid): JsonResponse
    {
        $source = UserCompanion::where('uuid', $uuid)->where('owner_uuid', $ownerUuid)->first();
        if (!$source) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Item not found');
        }

        $copy = new UserCompanion();
        $copy->id = $source->id;
        $copy->owner_uuid = $ownerUuid;
        $copy->save();

        return $this->respond(Response::HTTP_CREATED, 'Duplicate added', $copy->uuid);
    }

    // ─── Remove ───────────────────────────────────────────────────────────────

    private function deleteWarframe(string $ownerUuid, string $uuid): JsonResponse
    {
        $deleted = UserWarframe::where('uuid', $uuid)->where('owner_uuid', $ownerUuid)->delete();
        if (!$deleted) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Item not found');
        }

        return $this->respond(Response::HTTP_OK, 'Removed from collection', true);
    }

    private function deleteWeapon(string $ownerUuid, string $uuid): JsonResponse
    {
        $deleted = UserWeapon::where('uuid', $uuid)->where('owner_uuid', $ownerUuid)->delete();
        if (!$deleted) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Item not found');
        }

        return $this->respond(Response::HTTP_OK, 'Removed from collection', true);
    }

    private function deleteCompanion(string $ownerUuid, string $uuid): JsonResponse
    {
        $deleted = UserCompanion::where('uuid', $uuid)->where('owner_uuid', $ownerUuid)->delete();
        if (!$deleted) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Item not found');
        }

        return $this->respond(Response::HTTP_OK, 'Removed from collection', true);
    }
}
