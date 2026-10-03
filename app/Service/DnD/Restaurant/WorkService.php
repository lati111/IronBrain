<?php

namespace App\Service\DnD\Restaurant;

use App\Enum\DnD\Restaurant\WorkStation;
use App\Models\DnD\Restaurant\Employee;
use App\Models\DnD\Restaurant\Equipment;
use App\Models\DnD\Restaurant\Meal;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\Work;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The work areas of the kitchen. Prep and cleaning are queued in their area, and worked through in order by the
 * employees there as the clock moves, at their combined speed. An area without employees gets nothing done. Finished
 * jobs stay until the whole queue of their area is done, so the area's total progress only ever goes up
 */
class WorkService
{
    public function __construct(private readonly KitchenService $kitchenService)
    {
    }

    /**
     * Describes the staff and the work areas: each employee with the task they are on, and each area with its speed,
     * how far along its queue is in total, and the jobs still to do, each with its own progress and how many minutes
     * are left until it is done
     * @param Restaurant $restaurant The restaurant
     * @return array The employees and the work areas
     */
    public function describe(Restaurant $restaurant): array
    {
        $employees = Employee::where('restaurant_uuid', $restaurant->uuid)->orderBy('name')->get();

        $stations = [];
        $tasks = [];
        foreach (WorkStation::cases() as $station) {
            $speed = $this->getSpeed($employees, $station);
            $all = $this->getQueue($restaurant, $station, lock: false, withFinished: true);
            $queue = $all->filter(fn (Work $job) => !$job->isFinished())->values();
            $totalWork = $all->sum('work');

            $tasks[$station->value] = $queue->isNotEmpty()
                ? $this->describeTask($queue->first())
                : $station->label() . ' (idle)';

            // Minutes left count up through the queue, as each job waits for the ones before it
            $before = 0.0;
            $stations[$station->value] = [
                'speed' => $speed,
                // Null while there is nothing to do
                'progress' => $queue->isNotEmpty() && $totalWork > 0 ? round($all->sum('progress') / $totalWork, 4) : null,
                'jobs' => $queue->map(function (Work $job) use (&$before, $speed) {
                    $before += $job->getRemaining();
                    return [
                        'uuid' => $job->uuid,
                        'meal_uuid' => $job->meal_uuid,
                        'equipment_uuid' => $job->equipment_uuid,
                        'amount' => $job->amount,
                        'work' => $job->work,
                        'progress' => $job->work > 0 ? round($job->progress / $job->work, 4) : 0,
                        'minutes_left' => $speed > 0 ? (int) ceil(round($before / $speed, 4)) : null,
                    ];
                })->values()->all(),
            ];
        }

        return [
            'employees' => $employees->map(fn (Employee $employee) => [
                'uuid' => $employee->uuid,
                'name' => $employee->name,
                'icon' => $employee->icon !== null ? asset('img/' . $employee->icon) : null,
                'station' => $employee->station?->value,
                'task' => $employee->station !== null ? $tasks[$employee->station->value] : 'Idle',
            ])->values()->all(),
            'stations' => $stations,
        ];
    }

    //| Staff

    /**
     * Puts an employee to work in a work area, or takes them out of their area when none is given. Work done so far
     * is kept, as work is only done when the clock moves
     * @param Employee $employee The employee
     * @param WorkStation|null $station The work area, or null to stop working
     */
    public function assignEmployee(Employee $employee, ?WorkStation $station): void
    {
        $employee->station = $station;
        $employee->save();
    }

    //| Queueing

    /**
     * Queues a planned meal to be prepped in the prep area. A recipe without prep time is prepped straight away
     * @param Restaurant $restaurant The restaurant
     * @param Meal $meal The meal
     * @throws InvalidArgumentException Thrown when the meal isn't planned, or is already queued
     */
    public function queuePrep(Restaurant $restaurant, Meal $meal): void
    {
        if ($meal->status !== Meal::STATUS_PLANNED) {
            throw new InvalidArgumentException('Only planned meals can be prepped');
        }

        if (Work::where('meal_uuid', $meal->uuid)->whereColumn('progress', '<', 'work')->exists()) {
            throw new InvalidArgumentException($meal->getRecipe()->name . ' is already queued for prep');
        }

        DB::transaction(function () use ($restaurant, $meal) {
            $minutes = $meal->getRecipe()->prep_time;
            if ($minutes <= 0) {
                $this->kitchenService->finishPrep($restaurant, $meal);
                return;
            }

            $this->createJob($restaurant, WorkStation::PREP, $minutes, meal: $meal);
        });
    }

    /**
     * Queues dirty pieces of a piece of equipment to be cleaned in the cleaning area. Equipment without cleaning time
     * is cleaned straight away
     * @param Restaurant $restaurant The restaurant
     * @param Equipment $equipment The equipment
     * @param int $amount How many pieces to clean
     * @throws InvalidArgumentException Thrown when fewer pieces are dirty and not yet queued
     */
    public function queueCleaning(Restaurant $restaurant, Equipment $equipment, int $amount): void
    {
        DB::transaction(function () use ($restaurant, $equipment, $amount) {
            $equipment = Equipment::where('uuid', $equipment->uuid)->lockForUpdate()->first();
            $available = $this->getUnqueuedDirty($equipment);
            if ($amount < 1 || $amount > $available) {
                throw new InvalidArgumentException(sprintf('Only %d %s left to clean', $available, $equipment->name));
            }

            $this->queueEquipment($restaurant, $equipment, $amount);
        });
    }

    /**
     * Queues every dirty piece of equipment that isn't queued yet to be cleaned
     * @param Restaurant $restaurant The restaurant
     * @return int How many pieces were queued
     * @throws InvalidArgumentException Thrown when nothing is left to clean
     */
    public function queueAllCleaning(Restaurant $restaurant): int
    {
        return DB::transaction(function () use ($restaurant) {
            $dirty = Equipment::where('restaurant_uuid', $restaurant->uuid)->where('amount_dirty', '>', 0)
                ->orderBy('name')->lockForUpdate()->get();

            $pieces = 0;
            foreach ($dirty as $equipment) {
                $available = $this->getUnqueuedDirty($equipment);
                if ($available > 0) {
                    $this->queueEquipment($restaurant, $equipment, $available);
                    $pieces += $available;
                }
            }

            if ($pieces === 0) {
                throw new InvalidArgumentException('Nothing left to clean');
            }

            return $pieces;
        });
    }

    //| Clock

    /**
     * Moves the restaurant's time forward, with the work areas getting work done in the meantime
     * @param Restaurant $restaurant The restaurant
     * @param int $minutes How many minutes to move forward
     */
    public function advanceTime(Restaurant $restaurant, int $minutes): void
    {
        DB::transaction(function () use ($restaurant, $minutes) {
            $this->doWork($restaurant, $minutes);
            $this->kitchenService->advanceTime($restaurant, $minutes);
        });
    }

    /**
     * Sets the restaurant's time on its current date. Moving it forward gets work done in the meantime, while
     * turning it back undoes none
     * @param Restaurant $restaurant The restaurant
     * @param string $time The time as H:i
     */
    public function setTime(Restaurant $restaurant, string $time): void
    {
        $now = $restaurant->getNow();
        $target = Carbon::parse($now->format('Y-m-d') . ' ' . $time);
        $minutes = intdiv($target->getTimestamp() - $now->getTimestamp(), 60);

        DB::transaction(function () use ($restaurant, $time, $minutes) {
            $this->doWork($restaurant, $minutes);
            $this->kitchenService->setTime($restaurant, $time);
        });
    }

    /**
     * Passes time until a cooking meal is done, with the work areas getting work done in the meantime
     * @param Restaurant $restaurant The restaurant
     * @param Meal $meal The meal
     * @throws InvalidArgumentException Thrown when the meal isn't cooking
     */
    public function skipUntilDone(Restaurant $restaurant, Meal $meal): void
    {
        $this->advanceTime($restaurant, $this->kitchenService->getMinutesUntilDone($restaurant, $meal));
    }

    //| Helpers

    /**
     * Gets work done in every work area over a stretch of time. Each area works through its queue in order at the
     * combined speed of its employees, carrying time left over from a finished job on to the next
     * @param Restaurant $restaurant The restaurant
     * @param float $minutes How many minutes pass
     */
    private function doWork(Restaurant $restaurant, float $minutes): void
    {
        if ($minutes <= 0) {
            return;
        }

        $employees = Employee::where('restaurant_uuid', $restaurant->uuid)->whereNotNull('station')->get();

        foreach (WorkStation::cases() as $station) {
            $budget = round($minutes * $this->getSpeed($employees, $station), 4);
            if ($budget <= 0) {
                continue;
            }

            foreach ($this->getQueue($restaurant, $station) as $job) {
                $remaining = $job->getRemaining();
                if ($budget < $remaining) {
                    $job->progress = round($job->progress + $budget, 2);
                    $job->save();
                    break;
                }

                $budget = round($budget - $remaining, 4);
                $this->finishJob($restaurant, $job);
            }

            // Once everything in the area is done, its finished jobs are cleared, starting its total over
            if ($this->getQueue($restaurant, $station)->isEmpty()) {
                Work::where('restaurant_uuid', $restaurant->uuid)->where('station', $station->value)->delete();
            }
        }
    }

    /**
     * Finishes a job: the meal is prepped, or the pieces of equipment are clean. The job stays, marked as finished,
     * until the rest of its area's queue is done
     * @param Restaurant $restaurant The restaurant
     * @param Work $job The job
     */
    private function finishJob(Restaurant $restaurant, Work $job): void
    {
        if ($job->station === WorkStation::PREP) {
            $meal = $job->Meal;
            if ($meal !== null && $meal->status === Meal::STATUS_PLANNED) {
                $this->kitchenService->finishPrep($restaurant, $meal);
            }
        } else {
            $equipment = Equipment::where('uuid', $job->equipment_uuid)->lockForUpdate()->first();
            if ($equipment !== null) {
                $equipment->amount_dirty = max(0, $equipment->amount_dirty - $job->amount);
                $equipment->save();
            }
        }

        $job->progress = $job->work;
        $job->save();
    }

    /**
     * Queues pieces of equipment to be cleaned, or cleans them straight away when cleaning takes no time
     * @param Restaurant $restaurant The restaurant
     * @param Equipment $equipment The equipment, locked
     * @param int $amount How many pieces
     */
    private function queueEquipment(Restaurant $restaurant, Equipment $equipment, int $amount): void
    {
        $minutes = $equipment->cleaning_time * $amount;
        if ($minutes <= 0) {
            $equipment->amount_dirty = max(0, $equipment->amount_dirty - $amount);
            $equipment->save();
            return;
        }

        $this->createJob($restaurant, WorkStation::CLEANING, $minutes, equipment: $equipment, amount: $amount);
    }

    /**
     * Adds a job at the end of the queue of a work area
     */
    private function createJob(Restaurant $restaurant, WorkStation $station, float $minutes, ?Meal $meal = null, ?Equipment $equipment = null, int $amount = 1): Work
    {
        $job = new Work();
        $job->restaurant_uuid = $restaurant->uuid;
        $job->station = $station;
        $job->meal_uuid = $meal?->uuid;
        $job->equipment_uuid = $equipment?->uuid;
        $job->amount = $amount;
        $job->work = $minutes;
        $job->progress = 0;
        $job->order = (int) Work::where('restaurant_uuid', $restaurant->uuid)->where('station', $station->value)->max('order') + 1;
        $job->save();

        return $job;
    }

    /**
     * Gets the queue of a work area, in the order its jobs are worked on
     * @param bool $withFinished Whether to include the finished jobs that wait for the rest of the queue
     * @return Collection<Work> The jobs
     */
    private function getQueue(Restaurant $restaurant, WorkStation $station, bool $lock = true, bool $withFinished = false): Collection
    {
        $query = Work::where('restaurant_uuid', $restaurant->uuid)
            ->where('station', $station->value)
            ->with(['Meal.Recipe', 'Equipment'])
            ->orderBy('order')
            ->orderBy('created_at');

        if (!$withFinished) {
            $query->whereColumn('progress', '<', 'work');
        }

        return $lock ? $query->lockForUpdate()->get() : $query->get();
    }

    /**
     * Gets the combined speed of the employees working in a work area
     * @param Collection<Employee> $employees The restaurant's employees
     * @param WorkStation $station The work area
     * @return float The combined speed, or 0 when nobody works there
     */
    private function getSpeed(Collection $employees, WorkStation $station): float
    {
        return round($employees->filter(fn (Employee $employee) => $employee->station === $station)
            ->sum(fn (Employee $employee) => $employee->getSpeed($station)), 2);
    }

    /**
     * Gets how many dirty pieces of a piece of equipment aren't queued to be cleaned yet
     */
    private function getUnqueuedDirty(Equipment $equipment): int
    {
        $queued = (int) Work::where('equipment_uuid', $equipment->uuid)->whereColumn('progress', '<', 'work')->sum('amount');
        return max(0, $equipment->amount_dirty - $queued);
    }

    /**
     * Describes the job a work area is on, such as "Prepping Stew" or "Cleaning 3 Plate"
     */
    private function describeTask(Work $job): string
    {
        return $job->station === WorkStation::PREP
            ? 'Prepping ' . ($job->Meal?->Recipe?->name ?? 'a meal')
            : sprintf('Cleaning %d %s', $job->amount, $job->Equipment?->name ?? 'equipment');
    }
}
