<?php
namespace App\Http\Api\Modules\DnD\Restaurant;

use App\Enum\DnD\Restaurant\WorkStation;
use App\Http\Api\Modules\DnD\Restaurant\Traits\ResolvesRestaurant;
use App\Models\DnD\Restaurant\ActiveBoost;
use App\Models\DnD\Restaurant\Boost;
use App\Models\DnD\Restaurant\Conversion;
use App\Models\DnD\Restaurant\Employee;
use App\Models\DnD\Restaurant\Equipment;
use App\Models\DnD\Restaurant\Meal;
use App\Models\DnD\Restaurant\Table;
use App\Models\DnD\Restaurant\Work;
use App\Service\DnD\Restaurant\BoostActivationService;
use App\Service\DnD\Restaurant\ServiceStateService;
use App\Service\DnD\Restaurant\WorkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * The staff and the work areas during service: who works where or waits which tables, the jobs queued in the prep
 * and cleaning areas, and the boosts that speed them up
 */
class WorkApi extends AbstractServiceApi
{
    use ResolvesRestaurant;

    public function __construct(
        ServiceStateService $serviceStateService,
        private readonly WorkService $workService,
        private readonly BoostActivationService $boostActivationService,
    )
    {
        parent::__construct($serviceStateService);
    }

    //| Staff

    /**
     * Puts an employee to work in a work area, or takes them out of it when no work area is given
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $employee_uuid The uuid of the employee
     * @return JsonResponse The updated service state in JSON format
     */
    public function assignEmployee(Request $request, string $restaurant_uuid, string $employee_uuid): JsonResponse
    {
        $invalid = $this->validateRequest($request, [
            'station' => ['nullable', 'string', Rule::in(WorkStation::values())],
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

        if ($restaurant->isPlayer(Auth::user())) {
            $denied = $this->denyPlayer($employee);
            if ($denied !== null) {
                return $denied;
            }
        }

        $station = $request->filled('station') ? WorkStation::from($request->get('station')) : null;
        $this->workService->assignEmployee($employee, $station);

        $message = $station !== null
            ? sprintf('%s is working at %s', $employee->name, strtolower($station->label()))
            : $employee->name . ' stopped working';
        return $this->respond(Response::HTTP_OK, $message, $this->getState($restaurant));
    }

    /**
     * Has an employee wait a table, taking its orders, or leaves the table without a waiter when none is given
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $table_uuid The uuid of the table
     * @return JsonResponse The updated service state in JSON format
     */
    public function assignWaiter(Request $request, string $restaurant_uuid, string $table_uuid): JsonResponse
    {
        $invalid = $this->validateRequest($request, [
            'employee_uuid' => 'nullable|string|max:255',
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

        $employee = null;
        if ($request->filled('employee_uuid')) {
            $employee = $this->findInRestaurant(Employee::class, $restaurant, $request->get('employee_uuid'));
            if ($employee === null) {
                return $this->notFound('Employee');
            }
        }

        // Players only move their own employees, and can't take a table from someone else's waiter
        if ($restaurant->isPlayer(Auth::user())) {
            $current = $table->employee_uuid !== null ? Employee::where('uuid', $table->employee_uuid)->first() : null;
            $denied = $this->denyPlayer($employee) ?? $this->denyPlayer($current);
            if ($denied !== null) {
                return $denied;
            }
        }

        $this->workService->assignWaiter($table, $employee);

        $message = $employee !== null ? sprintf('%s is waiting %s', $employee->name, $table->name) : $table->name . ' has no waiter';
        return $this->respond(Response::HTTP_OK, $message, $this->getState($restaurant));
    }

    /**
     * Turns a player away from an employee that isn't assigned to them
     * @param Employee|null $employee The employee, or null when there is none
     * @return JsonResponse|null An error response when the employee belongs to someone else, or null when the player
     *                           may control them
     */
    private function denyPlayer(?Employee $employee): ?JsonResponse
    {
        if ($employee === null || $employee->player_uuid === Auth::user()->uuid) {
            return null;
        }

        return $this->respond(Response::HTTP_FORBIDDEN, $employee->name . ' isn\'t one of your employees');
    }

    //| Work areas

    /**
     * Moves a job to the front of its queue, so it is worked on next
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $work_uuid The uuid of the job
     * @return JsonResponse The updated service state in JSON format
     */
    public function prioritizeWork(string $restaurant_uuid, string $work_uuid): JsonResponse
    {
        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->notFound('Restaurant');
        }

        $job = $this->findInRestaurant(Work::class, $restaurant, $work_uuid);
        if ($job === null) {
            return $this->notFound('Job');
        }

        $this->workService->prioritize($job);
        return $this->respond(Response::HTTP_OK, 'Prioritized', $this->getState($restaurant));
    }

    /**
     * Queues dirty pieces of a piece of equipment to be cleaned in the cleaning area
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $equipment_uuid The uuid of the equipment
     * @return JsonResponse The updated service state in JSON format
     */
    public function cleanEquipment(Request $request, string $restaurant_uuid, string $equipment_uuid): JsonResponse
    {
        $invalid = $this->validateRequest($request, [
            'amount' => 'required|integer|min:1',
        ]);
        if ($invalid !== null) {
            return $invalid;
        }

        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->notFound('Restaurant');
        }

        $equipment = $this->findInRestaurant(Equipment::class, $restaurant, $equipment_uuid);
        if ($equipment === null) {
            return $this->notFound('Equipment');
        }

        $amount = (int) $request->get('amount');
        return $this->attempt($restaurant, sprintf('Queued %d %s for cleaning', $amount, $equipment->name),
            fn () => $this->workService->queueCleaning($restaurant, $equipment, $amount));
    }

    /**
     * Queues every dirty piece of equipment that isn't queued yet to be cleaned in the cleaning area
     * @param string $restaurant_uuid The uuid of the restaurant
     * @return JsonResponse The updated service state in JSON format
     */
    public function cleanAllEquipment(string $restaurant_uuid): JsonResponse
    {
        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->notFound('Restaurant');
        }

        return $this->attempt($restaurant, 'Queued all dirty equipment for cleaning', fn () => $this->workService->queueAllCleaning($restaurant));
    }

    //| Conversions

    /**
     * Queues an ingredient to be converted in the prep area, which takes time for every piece converted
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $conversion_uuid The uuid of the conversion
     * @return JsonResponse The updated service state in JSON format
     */
    public function queueConversion(Request $request, string $restaurant_uuid, string $conversion_uuid): JsonResponse
    {
        $invalid = $this->validateRequest($request, [
            'amount' => 'required|numeric|gt:0|max:999999',
        ]);
        if ($invalid !== null) {
            return $invalid;
        }

        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->notFound('Restaurant');
        }

        $conversion = $this->findInRestaurant(Conversion::class, $restaurant, $conversion_uuid);
        if ($conversion === null) {
            return $this->notFound('Conversion');
        }

        return $this->attempt($restaurant, 'Conversion queued',
            fn () => $this->workService->queueConversion($restaurant, $conversion, (float) $request->get('amount')));
    }

    /**
     * Cancels a conversion in the prep area, putting the ingredients it took back
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $work_uuid The uuid of the conversion's job
     * @return JsonResponse The updated service state in JSON format
     */
    public function cancelConversion(string $restaurant_uuid, string $work_uuid): JsonResponse
    {
        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->notFound('Restaurant');
        }

        $job = $this->findInRestaurant(Work::class, $restaurant, $work_uuid);
        if ($job === null) {
            return $this->notFound('Job');
        }

        return $this->attempt($restaurant, 'Conversion cancelled', fn () => $this->workService->cancelConversion($restaurant, $job));
    }

    //| Boosts

    /**
     * Activates a boost on what it is for: a piece of equipment, a prepped or cooking meal, or an area of the kitchen
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $boost_uuid The uuid of the boost
     * @return JsonResponse The updated service state in JSON format
     */
    public function activateBoost(Request $request, string $restaurant_uuid, string $boost_uuid): JsonResponse
    {
        $invalid = $this->validateRequest($request, [
            'equipment_uuid' => 'nullable|string|max:255',
            'meal_uuid'      => 'nullable|string|max:255',
            'area'           => ['nullable', 'string', Rule::in(array_keys(Boost::AREAS))],
            // A single cooker of the equipment, rather than all of it
            'cooker_slot'    => 'nullable|integer|min:1',
        ]);
        if ($invalid !== null) {
            return $invalid;
        }

        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->notFound('Restaurant');
        }

        $boost = $this->findInRestaurant(Boost::class, $restaurant, $boost_uuid);
        if ($boost === null) {
            return $this->notFound('Boost');
        }

        // A boost is dropped on a single thing, which is whichever of these is given
        $equipment = null;
        if ($request->filled('equipment_uuid')) {
            $equipment = $this->findInRestaurant(Equipment::class, $restaurant, $request->get('equipment_uuid'));
            if ($equipment === null) {
                return $this->notFound('Equipment');
            }
        }

        $meal = null;
        if ($request->filled('meal_uuid')) {
            $meal = $this->findInRestaurant(Meal::class, $restaurant, $request->get('meal_uuid'));
            if ($meal === null) {
                return $this->notFound('Meal');
            }
        }

        $area = $request->get('area');
        $cookerSlot = $equipment !== null && $request->filled('cooker_slot') ? (int) $request->get('cooker_slot') : null;

        return $this->attempt($restaurant, $boost->name . ' activated',
            fn () => $this->boostActivationService->activateBoost($restaurant, $boost, $equipment, $meal, $area, $cookerSlot));
    }

    /**
     * Cancels a boost that is active, so it stops right away
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $active_boost_uuid The uuid of the active boost
     * @return JsonResponse The updated service state in JSON format
     */
    public function cancelBoost(string $restaurant_uuid, string $active_boost_uuid): JsonResponse
    {
        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->notFound('Restaurant');
        }

        $active = $this->findInRestaurant(ActiveBoost::class, $restaurant, $active_boost_uuid);
        if ($active === null) {
            return $this->notFound('Boost');
        }

        return $this->attempt($restaurant, $active->name . ' cancelled',
            fn () => $this->boostActivationService->cancelBoost($restaurant, $active));
    }
}
