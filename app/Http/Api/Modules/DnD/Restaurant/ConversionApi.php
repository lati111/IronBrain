<?php
namespace App\Http\Api\Modules\DnD\Restaurant;

use App\Enum\ErrorEnum;
use App\Http\Api\AbstractApi;
use App\Http\Api\Modules\DnD\Restaurant\Traits\ResolvesRestaurant;
use App\Models\DnD\Restaurant\Conversion;
use App\Models\DnD\Restaurant\Ingredient;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\Storage;
use App\Service\DnD\Restaurant\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class ConversionApi extends AbstractApi
{
    use ResolvesRestaurant;

    public function __construct(private readonly InventoryService $inventoryService)
    {
    }

    /**
     * Creates a conversion of an ingredient into another
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @return JsonResponse The uuid of the conversion and the updated inventory state in JSON format
     */
    public function createConversion(Request $request, string $restaurant_uuid): JsonResponse
    {
        return $this->saveConversion($request, $restaurant_uuid, null);
    }

    /**
     * Updates a conversion. Every field is required, as a conversion is small enough to always be sent whole
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $conversion_uuid The uuid of the conversion
     * @return JsonResponse The uuid of the conversion and the updated inventory state in JSON format
     */
    public function editConversion(Request $request, string $restaurant_uuid, string $conversion_uuid): JsonResponse
    {
        return $this->saveConversion($request, $restaurant_uuid, $conversion_uuid);
    }

    /**
     * Deletes a conversion. Conversions already queued during service still finish
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $conversion_uuid The uuid of the conversion
     * @return JsonResponse The updated inventory state in JSON format
     */
    public function deleteConversion(string $restaurant_uuid, string $conversion_uuid): JsonResponse
    {
        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Restaurant not found');
        }

        $conversion = $this->getConversion($restaurant, $conversion_uuid);
        if ($conversion === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Conversion not found');
        }

        $conversion->delete();

        return $this->respond(Response::HTTP_OK, 'Conversion deleted', [
            'inventory' => $this->inventoryService->getInventory($restaurant),
        ]);
    }

    /**
     * Converts an amount of a stored ingredient straight away, from a stack in a storage space or the pile. What it
     * becomes goes in the same place
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $conversion_uuid The uuid of the conversion
     * @return JsonResponse The updated inventory state in JSON format
     */
    public function convert(Request $request, string $restaurant_uuid, string $conversion_uuid): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount'            => 'required|numeric|gt:0|max:999999',
            'from_storage_uuid' => 'required|string|max:255',
            'stack_uuid'        => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->respond(Response::HTTP_BAD_REQUEST, ErrorEnum::VALIDATION_FAIL, $validator->errors());
        }

        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Restaurant not found');
        }

        $conversion = $this->getConversion($restaurant, $conversion_uuid);
        if ($conversion === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Conversion not found');
        }

        $fromUuid = $request->get('from_storage_uuid');
        $from = $fromUuid === InventoryService::PILE
            ? InventoryService::PILE
            : Storage::where('uuid', $fromUuid)->where('restaurant_uuid', $restaurant->uuid)->first();
        if ($from === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Storage not found');
        }

        try {
            $this->inventoryService->convert($conversion, (float) $request->get('amount'), $from, $request->get('stack_uuid'));
        } catch (InvalidArgumentException $exception) {
            return $this->respond(Response::HTTP_BAD_REQUEST, $exception->getMessage());
        }

        return $this->respond(Response::HTTP_OK, 'Converted', [
            'inventory' => $this->inventoryService->getInventory($restaurant),
        ]);
    }

    /**
     * Creates or updates a conversion from the request
     * @param string|null $conversion_uuid The uuid of the conversion to update, or null to create one
     */
    private function saveConversion(Request $request, string $restaurant_uuid, ?string $conversion_uuid): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'input_uuid'    => 'required|string|max:255',
            'output_uuid'   => 'required|string|max:255|different:input_uuid',
            'output_amount' => 'required|numeric|gt:0|max:999999',
            'minutes'       => 'required|numeric|min:0|max:999999',
        ]);

        if ($validator->fails()) {
            return $this->respond(Response::HTTP_BAD_REQUEST, ErrorEnum::VALIDATION_FAIL, $validator->errors());
        }

        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Restaurant not found');
        }

        $conversion = $conversion_uuid !== null ? $this->getConversion($restaurant, $conversion_uuid) : new Conversion();
        if ($conversion === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Conversion not found');
        }

        $found = Ingredient::where('restaurant_uuid', $restaurant->uuid)
            ->whereIn('uuid', [$request->get('input_uuid'), $request->get('output_uuid')])
            ->count();
        if ($found !== 2) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Ingredient not found');
        }

        $conversion->restaurant_uuid = $restaurant->uuid;
        $conversion->input_uuid = $request->get('input_uuid');
        $conversion->output_uuid = $request->get('output_uuid');
        $conversion->output_amount = round((float) $request->get('output_amount'), 2);
        $conversion->minutes = round((float) $request->get('minutes'), 2);
        $conversion->save();

        return $this->respond($conversion_uuid === null ? Response::HTTP_CREATED : Response::HTTP_OK,
            $conversion_uuid === null ? 'Conversion created' : 'Conversion updated', [
                'uuid' => $conversion->uuid,
                'inventory' => $this->inventoryService->getInventory($restaurant),
            ]);
    }

    /**
     * Gets a conversion belonging to a restaurant
     * @return Conversion|null The conversion, or null if it does not belong to the restaurant
     */
    private function getConversion(Restaurant $restaurant, string $uuid): ?Conversion
    {
        return Conversion::where('uuid', $uuid)->where('restaurant_uuid', $restaurant->uuid)->first();
    }
}
