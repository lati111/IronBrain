<?php
namespace App\Http\Api\Modules\DnD\Restaurant;

use App\Enum\ErrorEnum;
use App\Http\Api\AbstractApi;
use App\Http\Api\Modules\DnD\Restaurant\Traits\ResolvesRestaurant;
use App\Models\DnD\Restaurant\Customer;
use App\Models\DnD\Restaurant\Employee;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\Room;
use App\Models\DnD\Restaurant\Table;
use App\Service\DnD\Restaurant\FloorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage as FileStorage;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class FloorApi extends AbstractApi
{
    use ResolvesRestaurant;

    /** @var string The folder room background images are saved in */
    private const string ROOM_IMAGE_PATH = 'modules/dnd/restaurant/room';
    /** @var string The folder employee icons are saved in */
    private const string EMPLOYEE_ICON_PATH = 'modules/dnd/restaurant/employee';

    public function __construct(private readonly FloorService $floorService)
    {
    }

    /**
     * Gets the full floor state of a restaurant
     * @param string $restaurant_uuid The uuid of the restaurant
     * @return JsonResponse The floor state in JSON format
     */
    public function getFloor(string $restaurant_uuid): JsonResponse
    {
        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Restaurant not found');
        }

        return $this->respond(Response::HTTP_OK, 'Floor retrieved', $this->floorService->getFloor($restaurant));
    }

    //| Rooms

    /**
     * Creates a new room at the end of the restaurant's rooms
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @return JsonResponse The updated floor state in JSON format
     */
    public function createRoom(Request $request, string $restaurant_uuid): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'             => 'required|string|max:64',
            'background_image' => 'nullable|image|max:5120',
        ]);

        if ($validator->fails()) {
            return $this->respond(Response::HTTP_BAD_REQUEST, ErrorEnum::VALIDATION_FAIL, $validator->errors());
        }

        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Restaurant not found');
        }

        $room = new Room();
        $room->restaurant_uuid = $restaurant->uuid;
        $room->name            = $request->get('name');
        $room->order           = (int) Room::where('restaurant_uuid', $restaurant->uuid)->max('order') + 1;

        if ($request->hasFile('background_image')) {
            $room->background_image = FileStorage::putFile(self::ROOM_IMAGE_PATH, $request->file('background_image'));
        }

        $room->save();

        return $this->respond(Response::HTTP_CREATED, 'Room created', [
            'floor' => $this->floorService->getFloor($restaurant),
        ]);
    }

    /**
     * Saves a new order for the restaurant's rooms
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @return JsonResponse The updated floor state in JSON format
     */
    public function reorderRooms(Request $request, string $restaurant_uuid): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'order'   => 'required|array',
            'order.*' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->respond(Response::HTTP_BAD_REQUEST, ErrorEnum::VALIDATION_FAIL, $validator->errors());
        }

        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Restaurant not found');
        }

        $order = array_values($request->get('order'));
        $existing = Room::where('restaurant_uuid', $restaurant->uuid)->pluck('uuid')->all();
        if (count($order) !== count(array_unique($order)) || array_diff($existing, $order) !== [] || array_diff($order, $existing) !== []) {
            return $this->respond(Response::HTTP_BAD_REQUEST, 'The order must contain every room exactly once');
        }

        DB::transaction(function () use ($order, $restaurant) {
            foreach ($order as $index => $uuid) {
                Room::where('restaurant_uuid', $restaurant->uuid)->where('uuid', $uuid)->update(['order' => $index]);
            }
        });

        return $this->respond(Response::HTTP_OK, 'Rooms reordered', [
            'floor' => $this->floorService->getFloor($restaurant),
        ]);
    }

    /**
     * Deletes a room. The tables placed in it are kept, but are no longer placed in a room
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $room_uuid The uuid of the room
     * @return JsonResponse The updated floor state in JSON format
     */
    public function deleteRoom(string $restaurant_uuid, string $room_uuid): JsonResponse
    {
        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Restaurant not found');
        }

        $room = $this->getRoom($restaurant, $room_uuid);
        if ($room === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Room not found');
        }

        $image = $room->background_image;
        $room->delete();
        if ($image !== null) {
            FileStorage::delete($image);
        }

        return $this->respond(Response::HTTP_OK, 'Room deleted', [
            'floor' => $this->floorService->getFloor($restaurant),
        ]);
    }

    //| Tables

    /**
     * Creates a new table, optionally placed in a room straight away
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @return JsonResponse The uuid of the table and the updated floor state in JSON format
     */
    public function createTable(Request $request, string $restaurant_uuid): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'      => 'required|string|max:64',
            'capacity'  => 'required|integer|min:1',
            'room_uuid' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->respond(Response::HTTP_BAD_REQUEST, ErrorEnum::VALIDATION_FAIL, $validator->errors());
        }

        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Restaurant not found');
        }

        $roomUuid = $request->get('room_uuid');
        if ($roomUuid !== null && $this->getRoom($restaurant, $roomUuid) === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Room not found');
        }

        $table = new Table();
        $table->restaurant_uuid = $restaurant->uuid;
        $table->room_uuid       = $roomUuid;
        $table->name            = $request->get('name');
        $table->capacity        = (int) $request->get('capacity');
        $table->save();

        return $this->respond(Response::HTTP_CREATED, 'Table created', [
            'uuid' => $table->uuid,
            'floor' => $this->floorService->getFloor($restaurant),
        ]);
    }

    /**
     * Updates an existing table. Any field that is left out keeps its current value
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $table_uuid The uuid of the table
     * @return JsonResponse The updated floor state in JSON format
     */
    public function editTable(Request $request, string $restaurant_uuid, string $table_uuid): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'     => 'nullable|string|max:64',
            'capacity' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return $this->respond(Response::HTTP_BAD_REQUEST, ErrorEnum::VALIDATION_FAIL, $validator->errors());
        }

        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Restaurant not found');
        }

        $table = $this->getTable($restaurant, $table_uuid);
        if ($table === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Table not found');
        }

        $capacity = $request->has('capacity') ? (int) $request->get('capacity') : $table->capacity;
        if ($capacity < $this->floorService->getHighestOccupiedSeat($table)) {
            return $this->respond(Response::HTTP_BAD_REQUEST, 'A customer is seated on a seat this would remove');
        }

        $table->name     = $request->get('name', $table->name);
        $table->capacity = $capacity;
        $table->save();

        return $this->respond(Response::HTTP_OK, 'Table updated', [
            'floor' => $this->floorService->getFloor($restaurant),
        ]);
    }

    /**
     * Places a table in a room, or takes it out of its room when no room is given
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $table_uuid The uuid of the table
     * @return JsonResponse The updated floor state in JSON format
     */
    public function moveTable(Request $request, string $restaurant_uuid, string $table_uuid): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'room_uuid' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->respond(Response::HTTP_BAD_REQUEST, ErrorEnum::VALIDATION_FAIL, $validator->errors());
        }

        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Restaurant not found');
        }

        $table = $this->getTable($restaurant, $table_uuid);
        if ($table === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Table not found');
        }

        $roomUuid = $request->get('room_uuid');
        if ($roomUuid !== null && $this->getRoom($restaurant, $roomUuid) === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Room not found');
        }

        $table->room_uuid = $roomUuid;
        $table->save();

        return $this->respond(Response::HTTP_OK, $roomUuid !== null ? 'Table placed' : 'Table removed from room', [
            'floor' => $this->floorService->getFloor($restaurant),
        ]);
    }

    /**
     * Deletes a table. Any customers seated at it are no longer seated
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $table_uuid The uuid of the table
     * @return JsonResponse The updated floor state in JSON format
     */
    public function deleteTable(string $restaurant_uuid, string $table_uuid): JsonResponse
    {
        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Restaurant not found');
        }

        $table = $this->getTable($restaurant, $table_uuid);
        if ($table === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Table not found');
        }

        // The foreign key only clears the table, so the seat is cleared along with it here
        DB::transaction(function () use ($table) {
            Customer::where('table_uuid', $table->uuid)->update(['table_uuid' => null, 'seat' => null]);
            $table->delete();
        });

        return $this->respond(Response::HTTP_OK, 'Table deleted', [
            'floor' => $this->floorService->getFloor($restaurant),
        ]);
    }

    //| Customers

    /**
     * Creates a new customer for the restaurant
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @return JsonResponse The uuid of the customer and the updated floor state in JSON format
     */
    public function createCustomer(Request $request, string $restaurant_uuid): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:64',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->respond(Response::HTTP_BAD_REQUEST, ErrorEnum::VALIDATION_FAIL, $validator->errors());
        }

        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Restaurant not found');
        }

        $customer = new Customer();
        $customer->restaurant_uuid = $restaurant->uuid;
        $customer->name            = $request->get('name');
        $customer->description     = $request->get('description');
        $customer->save();

        return $this->respond(Response::HTTP_CREATED, 'Customer created', [
            'uuid' => $customer->uuid,
            'floor' => $this->floorService->getFloor($restaurant),
        ]);
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
        $validator = Validator::make($request->all(), [
            'name'        => 'nullable|string|max:64',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->respond(Response::HTTP_BAD_REQUEST, ErrorEnum::VALIDATION_FAIL, $validator->errors());
        }

        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Restaurant not found');
        }

        $customer = $this->getCustomer($restaurant, $customer_uuid);
        if ($customer === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Customer not found');
        }

        $customer->name        = $request->get('name', $customer->name);
        $customer->description = $request->has('description') ? $request->get('description') : $customer->description;
        $customer->save();

        return $this->respond(Response::HTTP_OK, 'Customer updated', [
            'floor' => $this->floorService->getFloor($restaurant),
        ]);
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
        $validator = Validator::make($request->all(), [
            'table_uuid' => 'nullable|string|max:255',
            'seat'       => 'required_with:table_uuid|nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return $this->respond(Response::HTTP_BAD_REQUEST, ErrorEnum::VALIDATION_FAIL, $validator->errors());
        }

        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Restaurant not found');
        }

        $customer = $this->getCustomer($restaurant, $customer_uuid);
        if ($customer === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Customer not found');
        }

        $tableUuid = $request->get('table_uuid');
        $table = $tableUuid !== null ? $this->getTable($restaurant, $tableUuid) : null;
        if ($tableUuid !== null && $table === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Table not found');
        }

        try {
            $seat = $request->get('seat');
            $this->floorService->seatCustomer($customer, $table, $seat !== null ? (int) $seat : null);
        } catch (InvalidArgumentException $exception) {
            return $this->respond(Response::HTTP_BAD_REQUEST, $exception->getMessage());
        }

        return $this->respond(Response::HTTP_OK, $table !== null ? 'Customer seated' : 'Customer unseated', [
            'floor' => $this->floorService->getFloor($restaurant),
        ]);
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
            return $this->respond(Response::HTTP_NOT_FOUND, 'Restaurant not found');
        }

        $customer = $this->getCustomer($restaurant, $customer_uuid);
        if ($customer === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Customer not found');
        }

        $customer->delete();

        return $this->respond(Response::HTTP_OK, 'Customer deleted', [
            'floor' => $this->floorService->getFloor($restaurant),
        ]);
    }

    //| Employees

    /**
     * Creates a new employee for the restaurant
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @return JsonResponse The uuid of the employee and the updated floor state in JSON format
     */
    public function createEmployee(Request $request, string $restaurant_uuid): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'           => 'required|string|max:64',
            'icon'           => 'nullable|image|max:2048',
            'cleaning_speed' => 'nullable|numeric|min:0|max:100',
            'prep_speed'     => 'nullable|numeric|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return $this->respond(Response::HTTP_BAD_REQUEST, ErrorEnum::VALIDATION_FAIL, $validator->errors());
        }

        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Restaurant not found');
        }

        $employee = new Employee();
        $employee->restaurant_uuid = $restaurant->uuid;
        $employee->name            = $request->get('name');
        $employee->cleaning_speed  = round((float) ($request->get('cleaning_speed') ?? 1), 2);
        $employee->prep_speed      = round((float) ($request->get('prep_speed') ?? 1), 2);

        if ($request->hasFile('icon')) {
            $employee->icon = FileStorage::putFile(self::EMPLOYEE_ICON_PATH, $request->file('icon'));
        }

        $employee->save();

        return $this->respond(Response::HTTP_CREATED, 'Employee created', [
            'uuid' => $employee->uuid,
            'floor' => $this->floorService->getFloor($restaurant),
        ]);
    }

    /**
     * Updates an existing employee. Any field that is left out keeps its current value
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $employee_uuid The uuid of the employee
     * @return JsonResponse The updated floor state in JSON format
     */
    public function editEmployee(Request $request, string $restaurant_uuid, string $employee_uuid): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'           => 'nullable|string|max:64',
            'icon'           => 'nullable|image|max:2048',
            'cleaning_speed' => 'nullable|numeric|min:0|max:100',
            'prep_speed'     => 'nullable|numeric|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return $this->respond(Response::HTTP_BAD_REQUEST, ErrorEnum::VALIDATION_FAIL, $validator->errors());
        }

        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Restaurant not found');
        }

        $employee = $this->getEmployee($restaurant, $employee_uuid);
        if ($employee === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Employee not found');
        }

        $employee->name = $request->get('name') ?? $employee->name;
        if ($request->filled('cleaning_speed')) {
            $employee->cleaning_speed = round((float) $request->get('cleaning_speed'), 2);
        }
        if ($request->filled('prep_speed')) {
            $employee->prep_speed = round((float) $request->get('prep_speed'), 2);
        }

        if ($request->hasFile('icon')) {
            $oldIcon = $employee->icon;
            $employee->icon = FileStorage::putFile(self::EMPLOYEE_ICON_PATH, $request->file('icon'));
            if ($oldIcon !== null) {
                FileStorage::delete($oldIcon);
            }
        }

        $employee->save();

        return $this->respond(Response::HTTP_OK, 'Employee updated', [
            'floor' => $this->floorService->getFloor($restaurant),
        ]);
    }

    /**
     * Deletes an employee, along with their icon
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $employee_uuid The uuid of the employee
     * @return JsonResponse The updated floor state in JSON format
     */
    public function deleteEmployee(string $restaurant_uuid, string $employee_uuid): JsonResponse
    {
        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Restaurant not found');
        }

        $employee = $this->getEmployee($restaurant, $employee_uuid);
        if ($employee === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Employee not found');
        }

        $icon = $employee->icon;
        $employee->delete();
        if ($icon !== null) {
            FileStorage::delete($icon);
        }

        return $this->respond(Response::HTTP_OK, 'Employee deleted', [
            'floor' => $this->floorService->getFloor($restaurant),
        ]);
    }

    //| Helpers

    /**
     * Gets a room belonging to a restaurant
     * @param Restaurant $restaurant The restaurant
     * @param string $uuid The uuid of the room
     * @return Room|null The room, or null if it does not belong to the restaurant
     */
    private function getRoom(Restaurant $restaurant, string $uuid): ?Room
    {
        return Room::where('uuid', $uuid)->where('restaurant_uuid', $restaurant->uuid)->first();
    }

    /**
     * Gets a table belonging to a restaurant
     * @param Restaurant $restaurant The restaurant
     * @param string $uuid The uuid of the table
     * @return Table|null The table, or null if it does not belong to the restaurant
     */
    private function getTable(Restaurant $restaurant, string $uuid): ?Table
    {
        return Table::where('uuid', $uuid)->where('restaurant_uuid', $restaurant->uuid)->first();
    }

    /**
     * Gets a customer belonging to a restaurant
     * @param Restaurant $restaurant The restaurant
     * @param string $uuid The uuid of the customer
     * @return Customer|null The customer, or null if they do not belong to the restaurant
     */
    private function getCustomer(Restaurant $restaurant, string $uuid): ?Customer
    {
        return Customer::where('uuid', $uuid)->where('restaurant_uuid', $restaurant->uuid)->first();
    }

    /**
     * Gets an employee belonging to a restaurant
     * @param Restaurant $restaurant The restaurant
     * @param string $uuid The uuid of the employee
     * @return Employee|null The employee, or null if they do not belong to the restaurant
     */
    private function getEmployee(Restaurant $restaurant, string $uuid): ?Employee
    {
        return Employee::where('uuid', $uuid)->where('restaurant_uuid', $restaurant->uuid)->first();
    }
}
