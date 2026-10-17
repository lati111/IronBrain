<?php
namespace App\Http\Api\Modules\DnD\Restaurant;

use App\Http\Api\Modules\DnD\Restaurant\Traits\ResolvesRestaurant;
use App\Models\DnD\Restaurant\Employee;
use App\Models\DnD\Restaurant\Restaurant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage as FileStorage;
use Symfony\Component\HttpFoundation\Response;

/**
 * The restaurant's employees, and the players controlling them
 */
class EmployeeApi extends AbstractFloorApi
{
    use ResolvesRestaurant;

    /** @var string The folder employee icons are saved in */
    private const string EMPLOYEE_ICON_PATH = 'modules/dnd/restaurant/employee';

    /**
     * Creates a new employee for the restaurant
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @return JsonResponse The uuid of the employee and the updated floor state in JSON format
     */
    public function createEmployee(Request $request, string $restaurant_uuid): JsonResponse
    {
        $invalid = $this->validateRequest($request, [
            'name'           => 'required|string|max:64',
            'icon'           => 'nullable|image|max:2048',
            'cleaning_speed' => 'nullable|numeric|min:0|max:100',
            'prep_speed'     => 'nullable|numeric|min:0|max:100',
            'player_uuid'    => 'nullable|string|max:255',
        ]);
        if ($invalid !== null) {
            return $invalid;
        }

        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->notFound('Restaurant');
        }

        if (!$this->hasValidPlayer($request, $restaurant)) {
            return $this->notFound('Player');
        }

        $employee = new Employee();
        $employee->player_uuid     = $request->filled('player_uuid') ? $request->get('player_uuid') : null;
        $employee->restaurant_uuid = $restaurant->uuid;
        $employee->name            = $request->get('name');
        $employee->cleaning_speed  = round((float) ($request->get('cleaning_speed') ?? 1), 2);
        $employee->prep_speed      = round((float) ($request->get('prep_speed') ?? 1), 2);

        if ($request->hasFile('icon')) {
            $employee->icon = FileStorage::putFile(self::EMPLOYEE_ICON_PATH, $request->file('icon'));
        }

        $employee->save();

        return $this->respondWithFloor(Response::HTTP_CREATED, 'Employee created', $restaurant, ['uuid' => $employee->uuid]);
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
        $invalid = $this->validateRequest($request, [
            'name'           => 'nullable|string|max:64',
            'icon'           => 'nullable|image|max:2048',
            'cleaning_speed' => 'nullable|numeric|min:0|max:100',
            'prep_speed'     => 'nullable|numeric|min:0|max:100',
            // An empty player leaves the employee to the owner and managers, while leaving it out keeps the current one
            'player_uuid'    => 'nullable|string|max:255',
        ]);
        if ($invalid !== null) {
            return $invalid;
        }

        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->notFound('Restaurant');
        }

        $employee = $this->findInRestaurant(Employee::class, $restaurant, $employee_uuid);
        if ($employee === null) {
            return $this->notFound('Employee');
        }

        if (!$this->hasValidPlayer($request, $restaurant)) {
            return $this->notFound('Player');
        }

        if ($request->has('player_uuid')) {
            $employee->player_uuid = $request->filled('player_uuid') ? $request->get('player_uuid') : null;
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

        return $this->respondWithFloor(Response::HTTP_OK, 'Employee updated', $restaurant);
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
            return $this->notFound('Restaurant');
        }

        $employee = $this->findInRestaurant(Employee::class, $restaurant, $employee_uuid);
        if ($employee === null) {
            return $this->notFound('Employee');
        }

        $icon = $employee->icon;
        $employee->delete();
        if ($icon !== null) {
            FileStorage::delete($icon);
        }

        return $this->respondWithFloor(Response::HTTP_OK, 'Employee deleted', $restaurant);
    }

    /**
     * Checks that the player given to control an employee, if any, is one of the restaurant's players
     * @param Request $request The request parameters as given by Laravel
     * @param Restaurant $restaurant The restaurant
     * @return bool Whether no player is given, or the one given is a player of the restaurant
     */
    private function hasValidPlayer(Request $request, Restaurant $restaurant): bool
    {
        return !$request->filled('player_uuid') || $this->floorService->isPlayer($restaurant, $request->get('player_uuid'));
    }
}
