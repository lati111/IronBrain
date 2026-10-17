<?php

namespace App\Service\DnD\Restaurant;

use App\Enum\DnD\Restaurant\WorkStation;
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
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The work that takes time as the clock moves, done in the order it was queued unless a job is prioritised:
 * - Prep is worked through one meal or conversion at a time, by everyone at prep together at their combined speed
 * - Cleaning is done a piece at a time, each piece by a single cleaner at their own speed, so several cleaners clean
 *   several pieces side by side
 * - Waiters work through the jobs of the tables they wait one at a time: taking orders and bringing plated portions
 * An area without employees gets nothing done
 */
class WorkService
{
    public function __construct(
        private readonly WorkQueueService $workQueueService,
        private readonly KitchenService $kitchenService,
        private readonly InventoryService $inventoryService,
        private readonly BoostService $boostService,
    ) {
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

    //| Prep

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

            $this->workQueueService->createJob($restaurant, WorkStation::PREP, $minutes, meal: $meal);
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

            $job = $this->workQueueService->createJob($restaurant, WorkStation::PREP, $minutes);
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

            $this->workQueueService->clearIfDone($restaurant, WorkStation::PREP);
        });
    }

    //| Cleaning

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
            $this->workQueueService->createJob($restaurant, WorkStation::CLEANING, round($equipment->cleaning_time, 2), equipment: $equipment);
        }
    }

    /**
     * Gets how many dirty pieces of a piece of equipment aren't queued to be cleaned yet
     * @param Equipment $equipment The equipment
     * @return int How many pieces
     */
    private function getUnqueuedDirty(Equipment $equipment): int
    {
        $queued = (int) Work::where('equipment_uuid', $equipment->uuid)->whereColumn('progress', '<', 'work')->sum('amount');
        return max(0, $equipment->amount_dirty - $queued);
    }

    //| Waiting

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
            $this->workQueueService->finishTakingOrder($order, $restaurant->getNow());
            return;
        }

        $this->workQueueService->createJob($restaurant, WorkStation::WAITING, $minutes, table: $table, order: $order);
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
            $this->workQueueService->finishDelivery($plate, $restaurant->getNow());
            return;
        }

        $this->workQueueService->createJob($restaurant, WorkStation::WAITING, $minutes, table: $table, plate: $plate);
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
     * Moves the restaurant's time forward, passing midnight into the next date if needed, with the work areas getting
     * work done in the meantime
     * @param Restaurant $restaurant The restaurant
     * @param int $minutes How many minutes to move forward
     */
    public function advanceTime(Restaurant $restaurant, int $minutes): void
    {
        DB::transaction(function () use ($restaurant, $minutes) {
            $this->doWork($restaurant, $minutes);

            $restaurant->setNow($restaurant->getNow()->addMinutes($minutes));
            $restaurant->save();
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

        DB::transaction(function () use ($restaurant, $target, $minutes) {
            $this->doWork($restaurant, $minutes);

            $restaurant->setNow($target);
            $restaurant->save();
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

    //| Doing work

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

            $speed = $this->workQueueService->getSpeed($employees, $station);
            if ($speed <= 0) {
                continue;
            }

            $this->doPrep($restaurant, $speed, $start, $end);
            $this->workQueueService->clearIfDone($restaurant, $station);
        }

        foreach ($this->workQueueService->getWaiters($employees) as $waiter) {
            $this->doWaiting($waiter, $minutes, $start);
        }
    }

    /**
     * Gets prep done over a stretch of time. Everyone at prep works on the first job in the queue together, at their
     * combined speed times the job's own speed, which depends on the equipment and boosts of its meal. As boosts run
     * out partway through, the time is worked through in stretches between those moments
     * @param Restaurant $restaurant The restaurant
     * @param float $speed The combined speed of the employees at prep
     * @param Carbon $start When the stretch of time started, in the restaurant's time
     * @param Carbon $end When it ends
     */
    private function doPrep(Restaurant $restaurant, float $speed, Carbon $start, Carbon $end): void
    {
        $boosts = $this->boostService->getBoosts($restaurant, $start);
        $queue = $this->workQueueService->getQueue($restaurant, WorkStation::PREP)->values();
        $index = 0;
        $at = $start->copy();

        foreach ([...$this->boostService->getBoundaries($boosts, $start, $end), $end] as $until) {
            while ($index < $queue->count() && $at->lt($until)) {
                $job = $queue[$index];
                $rate = $speed * $this->workQueueService->getJobSpeed($job, $at, $boosts);
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
                $this->workQueueService->finishJob($restaurant, $job, $at);
                $index++;
            }

            $at = $until->copy();
        }
    }

    /**
     * Gets cleaning done over a stretch of time, each cleaner on their own piece at their own speed
     * @param Restaurant $restaurant The restaurant
     * @param Collection<Employee> $employees The restaurant's employees
     * @param float $minutes How many minutes pass
     * @param Carbon $start When the stretch of time started, in the restaurant's time
     */
    private function doCleaning(Restaurant $restaurant, Collection $employees, float $minutes, Carbon $start): void
    {
        $queue = $this->workQueueService->getQueue($restaurant, WorkStation::CLEANING);
        $schedule = $this->workQueueService->scheduleCleaning($restaurant, $queue, $this->workQueueService->getCleaners($employees), $start, $minutes);

        foreach ($queue as $job) {
            if (isset($schedule['finished'][$job->uuid])) {
                $this->workQueueService->finishJob($restaurant, $job, $start->copy()->addSeconds((int) round($schedule['finished'][$job->uuid] * 60)));
            } elseif (isset($schedule['progress'][$job->uuid])) {
                $job->progress = round($job->progress + $schedule['progress'][$job->uuid], 2);
                $job->save();
            }
        }

        $this->workQueueService->clearIfDone($restaurant, WorkStation::CLEANING);
    }

    /**
     * Has a waiter work through the jobs of their tables one at a time, at normal speed, over a stretch of time
     * @param Employee $waiter The waiter, with their tables
     * @param float $minutes How many minutes pass
     * @param Carbon $start When the stretch of time started, in the restaurant's time
     */
    private function doWaiting(Employee $waiter, float $minutes, Carbon $start): void
    {
        $budget = $minutes;
        $done = 0.0;
        foreach ($this->workQueueService->getWaitingQueue($waiter->Tables->pluck('uuid')->all()) as $job) {
            $remaining = $job->getRemaining();
            if ($budget < $remaining) {
                $job->progress = round($job->progress + $budget, 2);
                $job->save();
                break;
            }

            $budget = round($budget - $remaining, 4);
            $done += $remaining;
            $this->workQueueService->finishWaitingJob($job, $start->copy()->addSeconds((int) round($done * 60)));
        }
    }
}
