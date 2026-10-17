<?php
namespace App\Http\Api\Modules\DnD\Restaurant;

use App\Http\Api\Modules\DnD\Restaurant\Traits\ResolvesRestaurant;
use App\Models\DnD\Restaurant\Customer;
use App\Models\DnD\Restaurant\Table;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The restaurant's customers, and the seats at the tables they are seated on
 */
class CustomerApi extends AbstractFloorApi
{
    use ResolvesRestaurant;

    /**
     * Creates a new customer for the restaurant
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @return JsonResponse The uuid of the customer and the updated floor state in JSON format
     */
    public function createCustomer(Request $request, string $restaurant_uuid): JsonResponse
    {
        $invalid = $this->validateRequest($request, [
            'name'        => 'required|string|max:64',
            'description' => 'nullable|string',
        ]);
        if ($invalid !== null) {
            return $invalid;
        }

        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->notFound('Restaurant');
        }

        $customer = new Customer();
        $customer->restaurant_uuid = $restaurant->uuid;
        $customer->name            = $request->get('name');
        $customer->description     = $request->get('description');
        $customer->save();

        return $this->respondWithFloor(Response::HTTP_CREATED, 'Customer created', $restaurant, ['uuid' => $customer->uuid]);
    }

    /**
     * Updates an existing customer. Any field that is left out keeps its current value
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $customer_uuid The uuid of the customer
     * @return JsonResponse The updated floor state in JSON format
     */
    public function editCustomer(Request $request, string $restaurant_uuid, string $customer_uuid): JsonResponse
    {
        $invalid = $this->validateRequest($request, [
            'name'        => 'nullable|string|max:64',
            'description' => 'nullable|string',
        ]);
        if ($invalid !== null) {
            return $invalid;
        }

        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->notFound('Restaurant');
        }

        $customer = $this->findInRestaurant(Customer::class, $restaurant, $customer_uuid);
        if ($customer === null) {
            return $this->notFound('Customer');
        }

        $customer->name        = $request->get('name', $customer->name);
        $customer->description = $request->has('description') ? $request->get('description') : $customer->description;
        $customer->save();

        return $this->respondWithFloor(Response::HTTP_OK, 'Customer updated', $restaurant);
    }

    /**
     * Seats a customer on a seat of a table, or unseats them when no table is given. Unseating does not delete them
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $customer_uuid The uuid of the customer
     * @return JsonResponse The updated floor state in JSON format
     */
    public function seatCustomer(Request $request, string $restaurant_uuid, string $customer_uuid): JsonResponse
    {
        $invalid = $this->validateRequest($request, [
            'table_uuid' => 'nullable|string|max:255',
            'seat'       => 'required_with:table_uuid|nullable|integer|min:1',
        ]);
        if ($invalid !== null) {
            return $invalid;
        }

        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->notFound('Restaurant');
        }

        $customer = $this->findInRestaurant(Customer::class, $restaurant, $customer_uuid);
        if ($customer === null) {
            return $this->notFound('Customer');
        }

        $tableUuid = $request->get('table_uuid');
        $table = $tableUuid !== null ? $this->findInRestaurant(Table::class, $restaurant, $tableUuid) : null;
        if ($tableUuid !== null && $table === null) {
            return $this->notFound('Table');
        }

        try {
            $seat = $request->get('seat');
            $this->floorService->seatCustomer($customer, $table, $seat !== null ? (int) $seat : null);
        } catch (InvalidArgumentException $exception) {
            return $this->respond(Response::HTTP_BAD_REQUEST, $exception->getMessage());
        }

        return $this->respondWithFloor(Response::HTTP_OK, $table !== null ? 'Customer seated' : 'Customer unseated', $restaurant);
    }

    /**
     * Deletes a customer
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $customer_uuid The uuid of the customer
     * @return JsonResponse The updated floor state in JSON format
     */
    public function deleteCustomer(string $restaurant_uuid, string $customer_uuid): JsonResponse
    {
        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->notFound('Restaurant');
        }

        $customer = $this->findInRestaurant(Customer::class, $restaurant, $customer_uuid);
        if ($customer === null) {
            return $this->notFound('Customer');
        }

        $customer->delete();

        return $this->respondWithFloor(Response::HTTP_OK, 'Customer deleted', $restaurant);
    }
}
