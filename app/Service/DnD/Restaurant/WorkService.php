<?php

namespace App\Service\DnD\Restaurant;

use App\Enum\DnD\Restaurant\EquipmentCategory;
use App\Enum\DnD\Restaurant\EquipmentStage;
use App\Enum\DnD\Restaurant\WorkStation;
use App\Models\DnD\Restaurant\ActiveBoost;
use App\Models\DnD\Restaurant\Boost;
use App\Models\DnD\Restaurant\Conversion;
use App\Models\DnD\Restaurant\Employee;
use App\Models\DnD\Restaurant\Equipment;
use App\Models\DnD\Restaurant\Meal;
use App\Models\DnD\Restaurant\Order;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\ServedMeal;
use App\Models\DnD\Restaurant\Table;
use App\Models\DnD\Restaurant\Work;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The work that takes time as the clock moves, done in the order it was queued unless a job is prioritised:
 * - Prep is worked through one meal or conversion at a time, by everyone at prep together at their combined speed
 * - Cleaning is done a piece at a time, each piece by a single cleaner at their own speed, so several cleaners clean
 *   several pieces side by side
 * - Waiters work through the jobs of the tables they wait one at a time: taking orders and bringing plated portions
 * An area without employees gets nothing done. Finished prep and cleaning jobs stay until the whole queue of their
 * area is done, so the area's total progress only ever goes up
 */
class WorkService
{
    public function __construct(
        private readonly KitchenService $kitchenService,
        private readonly InventoryService $inventoryService,
        private readonly SatisfactionService $satisfactionService,
        private readonly BoostService $boostService,
    ) {
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
        $employees = Employee::where('restaurant_uuid', $restaurant->uuid)->with(['Tables', 'Player'])->orderBy('name')->get();
        $waiting = $this->describeWaiting($restaurant, $employees);

        $stations = [];
        $tasks = [];
        $employeeTasks = $waiting['tasks'];
        foreach (WorkStation::areas() as $station) {
            $speed = $this->getSpeed($employees, $station);
            $all = $this->getQueue($restaurant, $station, lock: false, withFinished: true);
            $queue = $all->filter(fn (Work $job) => !$job->isFinished())->values();
            $totalWork = $all->sum('work');

            $tasks[$station->value] = $queue->isNotEmpty()
                ? $this->describeTask($queue->first())
                : $station->label() . ' (idle)';

            // When each job is done, in minutes from now, or null while nobody works in the area
            $finishedAt = [];
            if ($station === WorkStation::CLEANING) {
                $schedule = $this->scheduleCleaning($restaurant, $queue, $this->getCleaners($employees), $restaurant->getNow(), null);
                $finishedAt = $schedule['finished'];
                foreach ($schedule['assigned'] as $cleanerUuid => $job) {
                    $employeeTasks[$cleanerUuid] = $this->describeTask($job);
                }

                // Cleaners work on their own piece, so those without one have nothing to do
                $tasks[$station->value] = $station->label() . ' (idle)';
            } elseif ($speed > 0) {
                // Prep counts up through the queue, as each meal waits for the ones before it, each at its own speed
                $now = $restaurant->getNow();
                $boosts = $this->boostService->getBoosts($restaurant, $now);
                $before = 0.0;
                foreach ($queue as $job) {
                    $before += $job->getRemaining() / $this->getJobSpeed($job, $now, $boosts);
                    $finishedAt[$job->uuid] = $before / $speed;
                }
            }

            $stations[$station->value] = [
                'speed' => $speed,
                // Null while there is nothing to do
                'progress' => $queue->isNotEmpty() && $totalWork > 0 ? round($all->sum('progress') / $totalWork, 4) : null,
                'jobs' => $queue->map(fn (Work $job) => [
                    'uuid' => $job->uuid,
                    'meal_uuid' => $job->meal_uuid,
                    'equipment_uuid' => $job->equipment_uuid,
                    // A conversion: how much of which ingredient becomes how much of which
                    'conversion' => $job->output_uuid !== null ? [
                        'input_name' => $job->Input?->name,
                        'input_amount' => $job->input_amount,
                        'output_name' => $job->Output?->name,
                        'output_amount' => $job->output_amount,
                    ] : null,
                    'amount' => $job->amount,
                    'work' => $job->work,
                    'progress' => $job->work > 0 ? round($job->progress / $job->work, 4) : 0,
                    'minutes_left' => isset($finishedAt[$job->uuid]) ? (int) ceil(round($finishedAt[$job->uuid], 4)) : null,
                ])->values()->all(),
            ];
        }

        return [
            'employees' => $employees->map(fn (Employee $employee) => [
                'uuid' => $employee->uuid,
                'name' => $employee->name,
                'icon' => $employee->icon !== null ? asset('img/' . $employee->icon) : null,
                'station' => $employee->station?->value,
                // The player controlling the employee, if any
                'player_uuid' => $employee->player_uuid,
                'player_name' => $employee->Player?->username,
                // The tables the employee waits, when they wait tables rather than work in a work area
                'tables' => $employee->Tables->pluck('uuid')->values()->all(),
                'task' => $this->describeEmployeeTask($employee, $tasks, $employeeTasks),
            ])->values()->all(),
            'stations' => $stations,
            'waiting' => $waiting['jobs'],
        ];
    }

    /**
     * Describes the waiters' jobs: each with how far along it is, and how many minutes are left until it is done,
     * counting the jobs before it for the same waiter, or null when nobody waits its table
     * @param Collection<Employee> $employees The restaurant's employees, with their tables
     * @return array The jobs, and the job each waiter is on, keyed by their uuid
     */
    private function describeWaiting(Restaurant $restaurant, Collection $employees): array
    {
        $jobs = [];
        $tasks = [];
        $waited = [];

        foreach ($employees->filter(fn (Employee $employee) => $employee->station === null && $employee->Tables->isNotEmpty()) as $waiter) {
            $before = 0.0;
            $queue = $this->getWaitingQueue($waiter->Tables->pluck('uuid')->all(), lock: false);
            foreach ($queue as $job) {
                $before += $job->getRemaining();
                $jobs[] = $this->describeWaitingJob($job, (int) ceil(round($before, 4)));
                $waited[] = $job->uuid;
            }

            if ($queue->isNotEmpty()) {
                $tasks[$waiter->uuid] = $this->describeTask($queue->first());
            }
        }

        // Jobs of tables nobody waits wait as well
        $unwaited = Work::where('restaurant_uuid', $restaurant->uuid)->where('station', WorkStation::WAITING->value)
            ->whereNotIn('uuid', $waited)->with(['Table', 'ServedMeal.Recipe'])->orderBy('order')->get();
        foreach ($unwaited as $job) {
            $jobs[] = $this->describeWaitingJob($job, null);
        }

        return ['jobs' => $jobs, 'tasks' => $tasks];
    }

    private function describeWaitingJob(Work $job, ?int $minutesLeft): array
    {
        return [
            'uuid' => $job->uuid,
            'table_uuid' => $job->table_uuid,
            'order_uuid' => $job->order_uuid,
            'served_meal_uuid' => $job->served_meal_uuid,
            'task' => $this->describeTask($job),
            'progress' => $job->work > 0 ? round($job->progress / $job->work, 4) : 0,
            'minutes_left' => $minutesLeft,
        ];
    }

    //| Staff

    /**
     * Puts an employee to work in a work area, which takes them off the tables they wait, or stops them working
     * altogether when no area is given. Work done so far is kept, as work is only done when the clock moves
     * @param Employee $employee The employee
     * @param WorkStation|null $station The work area, or null to stop working
     */
    public function assignEmployee(Employee $employee, ?WorkStation $station): void
    {
        DB::transaction(function () use ($employee, $station) {
            Table::where('employee_uuid', $employee->uuid)->update(['employee_uuid' => null]);

            $employee->station = $station;
            $employee->save();
        });
    }

    /**
     * Has an employee wait a table, taking its orders, which takes them out of their work area. An employee can wait
     * several tables, while a table has a single waiter. Without an employee, the table is left without a waiter
     * @param Table $table The table
     * @param Employee|null $employee The employee, or null to leave the table without a waiter
     */
    public function assignWaiter(Table $table, ?Employee $employee): void
    {
        DB::transaction(function () use ($table, $employee) {
            if ($employee !== null) {
                $employee->station = null;
                $employee->save();
            }

            $table->employee_uuid = $employee?->uuid;
            $table->save();
        });
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
     * Queues an ingredient to be converted in the prep area, taking it from the stacks closest to expiring first.
     * What it becomes goes in the pile once done, straight away when converting takes no time
     * @param Restaurant $restaurant The restaurant
     * @param Conversion $conversion The conversion
     * @param float $amount How much of the input to convert
     * @throws InvalidArgumentException Thrown when it would make nothing, or too little of the input is stored
     */
    public function queueConversion(Restaurant $restaurant, Conversion $conversion, float $amount): void
    {
        $amount = round($amount, 2);
        $output = $conversion->getOutputFor($amount);
        if ($output <= 0) {
            throw new InvalidArgumentException('Too little to convert');
        }

        DB::transaction(function () use ($restaurant, $conversion, $amount, $output) {
            $consumed = $this->inventoryService->takeForCooking($restaurant, $conversion->Input, $amount);

            $minutes = round($amount * $conversion->minutes, 2);
            if ($minutes <= 0) {
                $this->inventoryService->addToPile($restaurant, $conversion->Output, $output);
                return;
            }

            $job = $this->createJob($restaurant, WorkStation::PREP, $minutes);
            $job->input_uuid = $conversion->input_uuid;
            $job->input_amount = $amount;
            $job->output_uuid = $conversion->output_uuid;
            $job->output_amount = $output;
            $job->consumed = $consumed;
            $job->save();
        });
    }

    /**
     * Cancels a conversion that isn't done yet, putting the ingredients it took back where they came from
     * @param Restaurant $restaurant The restaurant
     * @param Work $job The conversion's job
     * @throws InvalidArgumentException Thrown when the job isn't a conversion, or is already done
     */
    public function cancelConversion(Restaurant $restaurant, Work $job): void
    {
        if ($job->output_uuid === null) {
            throw new InvalidArgumentException('Only conversions can be cancelled');
        }

        if ($job->isFinished()) {
            throw new InvalidArgumentException('This conversion is already done');
        }

        DB::transaction(function () use ($restaurant, $job) {
            $this->inventoryService->putBack($restaurant, $job->consumed ?? []);
            $job->delete();

            // Without anything left to do, the finished jobs are cleared, starting the total over
            if ($this->getQueue($restaurant, WorkStation::PREP)->isEmpty()) {
                Work::where('restaurant_uuid', $restaurant->uuid)->where('station', WorkStation::PREP->value)->delete();
            }
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

    /**
     * Has the waiter of a table take an order there, which takes them a while for everyone seated. An order taken
     * in no time is open straight away
     * @param Restaurant $restaurant The restaurant
     * @param Order $order The order being taken, which is taking until the waiter is done
     * @param Table $table The table
     * @param float $minutes How long taking it takes
     */
    public function queueOrderTaking(Restaurant $restaurant, Order $order, Table $table, float $minutes): void
    {
        if ($minutes <= 0) {
            $this->finishTakingOrder($order, $restaurant->getNow());
            return;
        }

        $this->createJob($restaurant, WorkStation::WAITING, $minutes, table: $table, order: $order);
    }

    /**
     * Has the waiter of a table bring a plated portion there. The portion counts as served once they arrive
     * @param Restaurant $restaurant The restaurant
     * @param ServedMeal $plate The plated portion, already set aside for the order entry it is brought for
     * @param Table $table The table
     * @param float $minutes How long bringing it takes
     */
    public function queueDelivery(Restaurant $restaurant, ServedMeal $plate, Table $table, float $minutes): void
    {
        if ($minutes <= 0) {
            $this->finishDelivery($plate, $restaurant->getNow());
            return;
        }

        $this->createJob($restaurant, WorkStation::WAITING, $minutes, table: $table, plate: $plate);
    }

    /**
     * Moves a job to the front of its queue, so it is worked on next. Cleaning moves every piece of the equipment
     * still to clean along with it, keeping them in order
     * @param Work $job The job
     */
    public function prioritize(Work $job): void
    {
        $jobs = collect([$job]);
        if ($job->station === WorkStation::CLEANING && $job->equipment_uuid !== null) {
            $jobs = Work::where('station', WorkStation::CLEANING->value)->where('equipment_uuid', $job->equipment_uuid)
                ->whereColumn('progress', '<', 'work')->orderBy('order')->orderBy('created_at')->get();
        }

        DB::transaction(function () use ($job, $jobs) {
            $first = (int) Work::where('restaurant_uuid', $job->restaurant_uuid)->where('station', $job->station->value)->min('order');
            foreach ($jobs->values() as $index => $moved) {
                $moved->order = $first - $jobs->count() + $index;
                $moved->save();
            }
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

        $employees = Employee::where('restaurant_uuid', $restaurant->uuid)->with('Tables')->get();
        $start = $restaurant->getNow();
        $end = $start->copy()->addSeconds((int) round($minutes * 60));

        // Meals in a cooker cook on by themselves, at the speed of their cooker, equipment and boosts
        $this->boostService->advanceCooking($restaurant, $start, $end);

        foreach (WorkStation::areas() as $station) {
            if ($station === WorkStation::CLEANING) {
                $this->doCleaning($restaurant, $employees, $minutes, $start);
                continue;
            }

            $speed = $this->getSpeed($employees, $station);
            if ($speed <= 0) {
                continue;
            }

            $this->doPrep($restaurant, $speed, $start, $end);

            // Once everything in the area is done, its finished jobs are cleared, starting its total over
            if ($this->getQueue($restaurant, $station)->isEmpty()) {
                Work::where('restaurant_uuid', $restaurant->uuid)->where('station', $station->value)->delete();
            }
        }

        // Each waiter works through the jobs of their tables one at a time, at normal speed
        foreach ($employees->filter(fn (Employee $employee) => $employee->station === null && $employee->Tables->isNotEmpty()) as $waiter) {
            $budget = $minutes;
            $done = 0.0;
            foreach ($this->getWaitingQueue($waiter->Tables->pluck('uuid')->all()) as $job) {
                $remaining = $job->getRemaining();
                if ($budget < $remaining) {
                    $job->progress = round($job->progress + $budget, 2);
                    $job->save();
                    break;
                }

                $budget = round($budget - $remaining, 4);
                $done += $remaining;
                $this->finishWaitingJob($job, $start->copy()->addSeconds((int) round($done * 60)));
            }
        }
    }

    /**
     * Gets prep done over a stretch of time. Everyone at prep works on the first job in the queue together, at their
     * combined speed times the job's own speed, which depends on the equipment and boosts of its meal. As boosts run
     * out partway through, the time is worked through in stretches between those moments
     * @param float $speed The combined speed of the employees at prep
     * @param Carbon $start When the stretch of time started, in the restaurant's time
     * @param Carbon $end When it ends
     */
    private function doPrep(Restaurant $restaurant, float $speed, Carbon $start, Carbon $end): void
    {
        $boosts = $this->boostService->getBoosts($restaurant, $start);
        $queue = $this->getQueue($restaurant, WorkStation::PREP)->values();
        $index = 0;
        $at = $start->copy();

        foreach ([...$this->boostService->getBoundaries($boosts, $start, $end), $end] as $until) {
            while ($index < $queue->count() && $at->lt($until)) {
                $job = $queue[$index];
                $rate = $speed * $this->getJobSpeed($job, $at, $boosts);
                $needed = $job->getRemaining() / $rate;
                $available = ($until->getTimestamp() - $at->getTimestamp()) / 60;

                if ($needed > $available) {
                    $job->progress = round($job->progress + $available * $rate, 2);
                    $job->save();
                    $at = $until->copy();
                    break;
                }

                // The job finished partway through, once the work before it and its own was done
                $at = $at->copy()->addSeconds((int) round($needed * 60));
                $this->finishJob($restaurant, $job, $at);
                $index++;
            }

            $at = $until->copy();
        }
    }

    /**
     * Gets how fast a job in the prep area goes at a moment: a meal at the speed its equipment and boosts give it, a
     * conversion at the speed the boosts on the prep area give it
     * @return float The speed, where 1 is normal
     */
    private function getJobSpeed(Work $job, Carbon $at, BaseCollection $boosts): float
    {
        return $job->Meal !== null
            ? $this->boostService->getMealSpeed($job->Meal, EquipmentStage::PREP, $at, $boosts)
            : $this->boostService->getAreaSpeed(Boost::AREA_PREP, $at, $boosts);
    }

    //| Boosts

    /**
     * Activates a boost during service on what it is for: a piece of equipment, a prepped or cooking meal, or an area
     * of the kitchen. Minutes it takes off are done straight away on the prep or cooking of what it is on, finishing
     * it when nothing is left. A meal gets its quality bonus. A speed bonus lasts the boost's duration
     * @param Restaurant $restaurant The restaurant
     * @param Boost $boost The boost
     * @param Equipment|null $equipment The equipment it is activated on, for equipment boosts
     * @param Meal|null $meal The meal it is activated on, for meal boosts
     * @param string|null $area The area it is activated on, one of the Boost::AREA constants, for area boosts
     * @param int|null $cookerSlot The single cooker of the equipment it is activated on, from 1, or null for all of it
     * @throws InvalidArgumentException Thrown when no service is ongoing, or it is activated on something it isn't for
     */
    public function activateBoost(Restaurant $restaurant, Boost $boost, ?Equipment $equipment, ?Meal $meal, ?string $area, ?int $cookerSlot = null): void
    {
        if (!$restaurant->isInService()) {
            throw new InvalidArgumentException('Boosts are only activated during service');
        }

        $now = $restaurant->getNow();
        match ($boost->target) {
            Boost::TARGET_EQUIPMENT => $equipment !== null ?: throw new InvalidArgumentException('Choose the equipment to boost'),
            Boost::TARGET_AREA => in_array($area, Boost::AREAS, true) ?: throw new InvalidArgumentException('Choose the area to boost'),
            default => $meal !== null ?: throw new InvalidArgumentException('Choose the meal to boost'),
        };

        if ($meal !== null && !in_array($meal->getStatusAt($now), [Meal::STATUS_PREPPED, Meal::STATUS_COOKING], true)) {
            throw new InvalidArgumentException('Only prepped or cooking meals can be boosted');
        }

        if ($cookerSlot !== null && ($equipment === null || $equipment->category !== EquipmentCategory::COOKER
                || $cookerSlot < 1 || $cookerSlot > $this->inventoryService->getTotalStock($equipment))) {
            throw new InvalidArgumentException('There is no such cooker to boost');
        }

        DB::transaction(function () use ($restaurant, $boost, $equipment, $meal, $area, $cookerSlot, $now) {
            if ($meal !== null && $boost->quality_bonus !== 0) {
                $meal->quality_bonus += $boost->quality_bonus;
                $meal->save();
            }

            if ($boost->minutes_off > 0) {
                $this->cutTime($restaurant, $boost->minutes_off, $now, $equipment, $meal, $area, $cookerSlot);
            }

            if ($boost->lasts()) {
                $active = new ActiveBoost();
                $active->restaurant_uuid = $restaurant->uuid;
                $active->boost_uuid = $boost->uuid;
                $active->name = $boost->name;
                $active->speed_bonus = $boost->speed_bonus;
                $active->equipment_uuid = $equipment?->uuid;
                $active->cooker_slot = $cookerSlot;
                $active->meal_uuid = $meal?->uuid;
                $active->area = $area;
                $active->starts_at = $now;
                $active->expires_at = $now->copy()->addMinutes($boost->duration);
                $active->save();

                // Meals in a cooker go faster from now on, so they are done sooner
                $this->boostService->rescheduleAll($restaurant, $now);
            }
        });
    }

    /**
     * Does minutes of work straight away on what a boost is activated on: a prepped meal gets a head start on cooking,
     * a cooking meal cooks on, meals being prepped get prep done, and pieces waiting to be cleaned get cleaning done.
     * Equipment counts for the meals using it in the stage they are in, a single cooker for the meal in it, and an area
     * for everything in it
     */
    private function cutTime(Restaurant $restaurant, float $minutes, Carbon $now, ?Equipment $equipment, ?Meal $meal, ?string $area, ?int $cookerSlot): void
    {
        if ($area === Boost::AREA_CLEANING) {
            $this->cutCleaning($restaurant, $minutes, $now);
            return;
        }

        $cooking = $this->boostService->getCookingMeals($restaurant)->filter(fn (Meal $candidate) => match (true) {
            $meal !== null => $candidate->uuid === $meal->uuid,
            $cookerSlot !== null => $candidate->cooker_uuid === $equipment->uuid && $candidate->cooker_slot === $cookerSlot,
            $equipment !== null => in_array($equipment->uuid, $this->boostService->getStageEquipment($candidate, EquipmentStage::COOKING), true),
            default => $area === Boost::AREA_COOKING,
        });
        foreach ($cooking as $candidate) {
            $candidate->cooking_progress = min((float) $candidate->Recipe->cooking_time, $candidate->cooking_progress + $minutes);
            $this->boostService->reschedule($candidate, $now);
        }

        if ($meal !== null && $meal->status === Meal::STATUS_PREPPED) {
            $meal->cooking_progress = min((float) $meal->getRecipe()->cooking_time, $meal->cooking_progress + $minutes);
            $meal->save();
        }

        if ($meal !== null || $area === Boost::AREA_COOKING || $cookerSlot !== null) {
            return;
        }

        $jobs = $this->getQueue($restaurant, WorkStation::PREP)->filter(fn (Work $job) => $equipment === null
            || ($job->Meal !== null && in_array($equipment->uuid, $this->boostService->getStageEquipment($job->Meal, EquipmentStage::PREP), true)));
        foreach ($jobs as $job) {
            $job->progress = round(min($job->work, $job->progress + $minutes), 2);
            $job->isFinished() ? $this->finishJob($restaurant, $job, $now) : $job->save();
        }

        if ($jobs->isNotEmpty() && $this->getQueue($restaurant, WorkStation::PREP)->isEmpty()) {
            Work::where('restaurant_uuid', $restaurant->uuid)->where('station', WorkStation::PREP->value)->delete();
        }
    }

    /**
     * Does minutes of cleaning straight away on every piece waiting to be cleaned, as a boost on the cleaning area does
     */
    private function cutCleaning(Restaurant $restaurant, float $minutes, Carbon $now): void
    {
        $jobs = $this->getQueue($restaurant, WorkStation::CLEANING);
        foreach ($jobs as $job) {
            $job->progress = round(min($job->work, $job->progress + $minutes), 2);
            $job->isFinished() ? $this->finishJob($restaurant, $job, $now) : $job->save();
        }

        if ($jobs->isNotEmpty() && $this->getQueue($restaurant, WorkStation::CLEANING)->isEmpty()) {
            Work::where('restaurant_uuid', $restaurant->uuid)->where('station', WorkStation::CLEANING->value)->delete();
        }
    }

    /**
     * Gets cleaning done over a stretch of time, each cleaner on their own piece at their own speed
     * @param Collection<Employee> $employees The restaurant's employees
     * @param float $minutes How many minutes pass
     * @param Carbon $start When the stretch of time started, in the restaurant's time
     */
    private function doCleaning(Restaurant $restaurant, Collection $employees, float $minutes, Carbon $start): void
    {
        $queue = $this->getQueue($restaurant, WorkStation::CLEANING);
        $schedule = $this->scheduleCleaning($restaurant, $queue, $this->getCleaners($employees), $start, $minutes);

        foreach ($queue as $job) {
            if (isset($schedule['finished'][$job->uuid])) {
                $this->finishJob($restaurant, $job, $start->copy()->addSeconds((int) round($schedule['finished'][$job->uuid] * 60)));
            } elseif (isset($schedule['progress'][$job->uuid])) {
                $job->progress = round($job->progress + $schedule['progress'][$job->uuid], 2);
                $job->save();
            }
        }

        // Once everything is clean, the finished jobs are cleared, starting the total over
        if ($this->getQueue($restaurant, WorkStation::CLEANING)->isEmpty()) {
            Work::where('restaurant_uuid', $restaurant->uuid)->where('station', WorkStation::CLEANING->value)->delete();
        }
    }

    /**
     * Works out how cleaning goes: the fastest cleaners take the first pieces in the queue, and each takes the next
     * piece as soon as theirs is clean. Boosts on the cleaning area make everyone clean faster while they last
     * @param Collection<Work> $queue The pieces still to clean, in order
     * @param array<string, float> $cleaners The speed of each cleaner, keyed by their uuid, fastest first
     * @param Carbon $start When cleaning starts, in the restaurant's time
     * @param float|null $limit How many minutes to work out, or null to work out until everything is clean
     * @return array When each finished piece was done in minutes from the start, the work done on the others, and
     *               the piece each cleaner starts on
     */
    private function scheduleCleaning(Restaurant $restaurant, Collection $queue, array $cleaners, Carbon $start, ?float $limit): array
    {
        // How much faster everyone cleans until the next boost runs out, and when that is in minutes from the start
        $boosts = $this->boostService->getBoosts($restaurant, $start);
        $boundaries = array_map(fn (Carbon $moment) => ($moment->getTimestamp() - $start->getTimestamp()) / 60,
            $this->boostService->getBoundaries($boosts, $start, null));
        $factor = fn (float $time) => $this->boostService->getAreaSpeed(Boost::AREA_CLEANING, $start->copy()->addSeconds((int) round($time * 60)), $boosts);

        $remaining = $queue->mapWithKeys(fn (Work $job) => [$job->uuid => $job->getRemaining()])->all();
        $pending = $queue->values()->all();
        $active = [];
        foreach ($cleaners as $cleanerUuid => $speed) {
            if ($pending !== []) {
                $active[$cleanerUuid] = array_shift($pending);
            }
        }

        $schedule = ['finished' => [], 'progress' => [], 'assigned' => $active];
        $time = 0.0;
        while ($active !== []) {
            // Until the first of the pieces being cleaned is done, or the next boost runs out
            $rate = $factor($time);
            $step = min(array_map(fn (string $cleanerUuid) => $remaining[$active[$cleanerUuid]->uuid] / ($cleaners[$cleanerUuid] * $rate), array_keys($active)));
            $next = collect($boundaries)->first(fn (float $boundary) => $boundary > $time + 0.0001);
            if ($next !== null && $time + $step > $next) {
                $step = $next - $time;
            }
            $last = $limit !== null && $time + $step > $limit;
            if ($last) {
                $step = $limit - $time;
            }
            $time += $step;

            foreach ($active as $cleanerUuid => $job) {
                $work = $step * $cleaners[$cleanerUuid] * $rate;
                $remaining[$job->uuid] -= $work;
                $schedule['progress'][$job->uuid] = ($schedule['progress'][$job->uuid] ?? 0) + $work;

                if (!$last && $remaining[$job->uuid] <= 0.0001) {
                    $schedule['finished'][$job->uuid] = $time;
                    unset($active[$cleanerUuid]);
                    if ($pending !== []) {
                        $active[$cleanerUuid] = array_shift($pending);
                    }
                }
            }

            if ($last) {
                break;
            }
        }

        return $schedule;
    }

    /**
     * Gets the employees cleaning, with their speed, fastest first
     * @param Collection<Employee> $employees The restaurant's employees
     * @return array<string, float> The speed of each cleaner, keyed by their uuid
     */
    private function getCleaners(Collection $employees): array
    {
        $cleaners = $employees
            ->filter(fn (Employee $employee) => $employee->station === WorkStation::CLEANING && $employee->cleaning_speed > 0)
            ->sortBy([['cleaning_speed', 'desc'], ['name', 'asc']])
            ->mapWithKeys(fn (Employee $employee) => [$employee->uuid => $employee->cleaning_speed])
            ->all();

        return $cleaners;
    }

    /**
     * Finishes a waiter's job: the order is taken, or the portion is served. Unlike the work areas, waiters have no
     * total to keep, so the job is cleared straight away
     * @param Work $job The job
     * @param Carbon $at When it finished, in the restaurant's time
     */
    private function finishWaitingJob(Work $job, Carbon $at): void
    {
        if ($job->Order !== null) {
            $this->finishTakingOrder($job->Order, $at);
        }

        if ($job->ServedMeal !== null) {
            $this->finishDelivery($job->ServedMeal, $at);
        }

        $job->delete();
    }

    /**
     * The waiter is done taking an order, which opens it to be served
     */
    private function finishTakingOrder(Order $order, Carbon $at): void
    {
        if ($order->status !== Order::STATUS_TAKING) {
            return;
        }

        $order->status = Order::STATUS_OPEN;
        $order->recalculate($at);
        $order->save();
    }

    /**
     * The waiter arrived with a plated portion, which serves the order entry it was set aside for. The customer rates
     * how satisfied they are with it there and then
     */
    private function finishDelivery(ServedMeal $plate, Carbon $at): void
    {
        $item = $plate->OrderItem;
        if ($item === null || $item->served_at !== null) {
            return;
        }

        $item->served_at = $at;
        $item->satisfaction = $this->satisfactionService->rate($item, $plate, $at);
        $item->save();

        $order = $item->Order;
        $order->recalculate($at);
        $order->save();
    }

    /**
     * Finishes a job: the meal is prepped, the converted ingredient goes in the pile, or the pieces of equipment are clean. The job stays, marked as finished,
     * until the rest of its area's queue is done
     * @param Restaurant $restaurant The restaurant
     * @param Work $job The job
     * @param Carbon $at When it finished, in the restaurant's time
     */
    private function finishJob(Restaurant $restaurant, Work $job, Carbon $at): void
    {
        if ($job->station === WorkStation::PREP && $job->output_uuid !== null) {
            if ($job->Output !== null) {
                $this->inventoryService->addToPile($restaurant, $job->Output, $job->output_amount);
            }
        } elseif ($job->station === WorkStation::PREP) {
            $meal = $job->Meal;
            if ($meal !== null && $meal->status === Meal::STATUS_PLANNED) {
                $this->kitchenService->finishPrep($restaurant, $meal, $at);
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
        // Cleaning times can be fractions of a minute, and work is kept with two decimals
        $minutes = round($equipment->cleaning_time * $amount, 2);
        if ($minutes <= 0) {
            $equipment->amount_dirty = max(0, $equipment->amount_dirty - $amount);
            $equipment->save();
            return;
        }

        // Each piece is a job of its own, as a piece is cleaned by a single cleaner
        for ($piece = 0; $piece < $amount; $piece++) {
            $this->createJob($restaurant, WorkStation::CLEANING, round($equipment->cleaning_time, 2), equipment: $equipment);
        }
    }

    /**
     * Adds a job at the end of a queue: of a work area, or of the waiter of a table
     */
    private function createJob(
        Restaurant $restaurant, WorkStation $station, float $minutes, ?Meal $meal = null, ?Equipment $equipment = null,
        int $amount = 1, ?Table $table = null, ?Order $order = null, ?ServedMeal $plate = null,
    ): Work
    {
        $job = new Work();
        $job->restaurant_uuid = $restaurant->uuid;
        $job->station = $station;
        $job->meal_uuid = $meal?->uuid;
        $job->equipment_uuid = $equipment?->uuid;
        $job->table_uuid = $table?->uuid;
        $job->order_uuid = $order?->uuid;
        $job->served_meal_uuid = $plate?->uuid;
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
            ->with(['Meal.Recipe', 'Equipment', 'Input', 'Output'])
            ->orderBy('order')
            ->orderBy('created_at');

        if (!$withFinished) {
            $query->whereColumn('progress', '<', 'work');
        }

        return $lock ? $query->lockForUpdate()->get() : $query->get();
    }

    /**
     * Gets a waiter's queue: the jobs of the tables they wait, in the order they were given
     * @param string[] $tableUuids The uuids of the tables
     * @return Collection<Work> The jobs
     */
    private function getWaitingQueue(array $tableUuids, bool $lock = true): Collection
    {
        $query = Work::where('station', WorkStation::WAITING->value)
            ->whereIn('table_uuid', $tableUuids)
            ->with(['Table', 'Order', 'ServedMeal.Recipe', 'ServedMeal.OrderItem'])
            ->orderBy('order')
            ->orderBy('created_at');

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
     * Describes what an employee is doing: the job of their work area, the job they are on as a waiter, the tables
     * they wait, or nothing
     * @param array<string, string> $tasks The job of each work area, keyed by its code
     * @param array<string, string> $employeeTasks The job of employees working on their own, such as cleaners and
     *                                             waiters, keyed by their uuid
     */
    private function describeEmployeeTask(Employee $employee, array $tasks, array $employeeTasks): string
    {
        if (isset($employeeTasks[$employee->uuid])) {
            return $employeeTasks[$employee->uuid];
        }

        if ($employee->station !== null) {
            return $tasks[$employee->station->value];
        }

        if ($employee->Tables->isNotEmpty()) {
            return 'Waiting ' . $employee->Tables->pluck('name')->sort()->join(', ');
        }

        return 'Idle';
    }

    /**
     * Describes a job, such as "Prepping Stew", "Cleaning 3 Plate", "Taking an order at Booth" or "Bringing Stew to Booth"
     */
    private function describeTask(Work $job): string
    {
        return match ($job->station) {
            WorkStation::PREP => $job->output_uuid !== null
                ? sprintf('Converting %s %s', $job->input_amount + 0, $job->Input?->name ?? 'ingredients')
                : 'Prepping ' . ($job->Meal?->Recipe?->name ?? 'a meal'),
            WorkStation::CLEANING => sprintf('Cleaning %d %s', $job->amount, $job->Equipment?->name ?? 'equipment'),
            WorkStation::WAITING => $job->order_uuid !== null
                ? 'Taking an order at ' . ($job->Table?->name ?? 'a table')
                : sprintf('Bringing %s to %s', $job->ServedMeal?->Recipe?->name ?? 'a plate', $job->Table?->name ?? 'a table'),
        };
    }
}
