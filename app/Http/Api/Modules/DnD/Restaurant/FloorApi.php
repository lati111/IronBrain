<?php
namespace App\Http\Api\Modules\DnD\Restaurant;

use App\Http\Api\Modules\DnD\Restaurant\Traits\ResolvesRestaurant;
use App\Models\DnD\Restaurant\Customer;
use App\Models\DnD\Restaurant\Room;
use App\Models\DnD\Restaurant\Table;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage as FileStorage;
use Symfony\Component\HttpFoundation\Response;

/**
 * The restaurant's floor: its rooms, and the tables placed in them
 */
class FloorApi extends AbstractFloorApi
{
    use ResolvesRestaurant;

    /** @var string The folder room background images are saved in */
    private const string ROOM_IMAGE_PATH = 'modules/dnd/restaurant/room';

    /**
     * Gets the full floor state of a restaurant
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @return JsonResponse The floor state in JSON format
     */
    public function getFloor(Request $request, string $restaurant_uuid): JsonResponse
    {
        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->notFound('Restaurant');
        }

        // A refresh that gives the version it last saw only gets told nothing changed, when that is the case
        if ($request->filled('version') && (int) $request->get('version') === $restaurant->state_version) {
            return $this->respond(Response::HTTP_OK, 'Unchanged', ['unchanged' => true, 'version' => $restaurant->state_version]);
        }

        return $this->respond(Response::HTTP_OK, 'Floor retrieved',
            $this->floorService->getFloor($restaurant) + ['version' => $restaurant->state_version]);
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
        $invalid = $this->validateRequest($request, [
            'name'             => 'required|string|max:64',
            'background_image' => 'nullable|image|max:5120',
        ]);
        if ($invalid !== null) {
            return $invalid;
        }

        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->notFound('Restaurant');
        }

        $room = new Room();
        $room->restaurant_uuid = $restaurant->uuid;
        $room->name            = $request->get('name');
        $room->order           = (int) Room::where('restaurant_uuid', $restaurant->uuid)->max('order') + 1;

        if ($request->hasFile('background_image')) {
            $room->background_image = FileStorage::putFile(self::ROOM_IMAGE_PATH, $request->file('background_image'));
        }

        $room->save();

        return $this->respondWithFloor(Response::HTTP_CREATED, 'Room created', $restaurant);
    }

    /**
     * Saves a new order for the restaurant's rooms
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @return JsonResponse The updated floor state in JSON format
     */
    public function reorderRooms(Request $request, string $restaurant_uuid): JsonResponse
    {
        $invalid = $this->validateRequest($request, [
            'order'   => 'required|array',
            'order.*' => 'required|string|max:255',
        ]);
        if ($invalid !== null) {
            return $invalid;
        }

        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->notFound('Restaurant');
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

        return $this->respondWithFloor(Response::HTTP_OK, 'Rooms reordered', $restaurant);
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
            return $this->notFound('Restaurant');
        }

        $room = $this->findInRestaurant(Room::class, $restaurant, $room_uuid);
        if ($room === null) {
            return $this->notFound('Room');
        }

        $image = $room->background_image;
        $room->delete();
        if ($image !== null) {
            FileStorage::delete($image);
        }

        return $this->respondWithFloor(Response::HTTP_OK, 'Room deleted', $restaurant);
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
        $invalid = $this->validateRequest($request, [
            'name'      => 'required|string|max:64',
            'capacity'  => 'required|integer|min:1',
            'room_uuid' => 'nullable|string|max:255',
        ]);
        if ($invalid !== null) {
            return $invalid;
        }

        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->notFound('Restaurant');
        }

        $roomUuid = $request->get('room_uuid');
        if ($roomUuid !== null && $this->findInRestaurant(Room::class, $restaurant, $roomUuid) === null) {
            return $this->notFound('Room');
        }

        $table = new Table();
        $table->restaurant_uuid = $restaurant->uuid;
        $table->room_uuid       = $roomUuid;
        $table->name            = $request->get('name');
        $table->capacity        = (int) $request->get('capacity');
        $table->save();

        return $this->respondWithFloor(Response::HTTP_CREATED, 'Table created', $restaurant, ['uuid' => $table->uuid]);
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
        $invalid = $this->validateRequest($request, [
            'name'     => 'nullable|string|max:64',
            'capacity' => 'nullable|integer|min:1',
        ]);
        if ($invalid !== null) {
            return $invalid;
        }

        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->notFound('Restaurant');
        }

        $table = $this->findInRestaurant(Table::class, $restaurant, $table_uuid);
        if ($table === null) {
            return $this->notFound('Table');
        }

        $capacity = $request->has('capacity') ? (int) $request->get('capacity') : $table->capacity;
        if ($capacity < $this->floorService->getHighestOccupiedSeat($table)) {
            return $this->respond(Response::HTTP_BAD_REQUEST, 'A customer is seated on a seat this would remove');
        }

        $table->name     = $request->get('name', $table->name);
        $table->capacity = $capacity;
        $table->save();

        return $this->respondWithFloor(Response::HTTP_OK, 'Table updated', $restaurant);
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
        $invalid = $this->validateRequest($request, [
            'room_uuid' => 'nullable|string|max:255',
        ]);
        if ($invalid !== null) {
            return $invalid;
        }

        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->notFound('Restaurant');
        }

        $table = $this->findInRestaurant(Table::class, $restaurant, $table_uuid);
        if ($table === null) {
            return $this->notFound('Table');
        }

        $roomUuid = $request->get('room_uuid');
        if ($roomUuid !== null && $this->findInRestaurant(Room::class, $restaurant, $roomUuid) === null) {
            return $this->notFound('Room');
        }

        $table->room_uuid = $roomUuid;
        $table->save();

        return $this->respondWithFloor(Response::HTTP_OK, $roomUuid !== null ? 'Table placed' : 'Table removed from room', $restaurant);
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
            return $this->notFound('Restaurant');
        }

        $table = $this->findInRestaurant(Table::class, $restaurant, $table_uuid);
        if ($table === null) {
            return $this->notFound('Table');
        }

        // The foreign key only clears the table, so the seat is cleared along with it here
        DB::transaction(function () use ($table) {
            Customer::where('table_uuid', $table->uuid)->update(['table_uuid' => null, 'seat' => null]);
            $table->delete();
        });

        return $this->respondWithFloor(Response::HTTP_OK, 'Table deleted', $restaurant);
    }
}
