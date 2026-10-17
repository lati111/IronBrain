<?php

namespace App\Service\DnD\Restaurant;

use App\Enum\DnD\Restaurant\EquipmentStage;
use App\Enum\DnD\Restaurant\WorkStation;
use App\Models\DnD\Restaurant\Boost;
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

/**
 * The queues of the work areas and of the waiters: adding jobs to them, looking them up, how fast they are worked
 * through, and what happens once a job is done. Finished prep and cleaning jobs stay until the whole queue of their
 * area is done, so the area's total progress only ever goes up
 */
class WorkQueueService
{
    public function __construct(
        private readonly KitchenService $kitchenService,
        private readonly InventoryService $inventoryService,
        private readonly SatisfactionService $satisfactionService,
        private readonly BoostService $boostService,
    ) {
    }

    //| Queues

    /**
     * Adds a job at the end of a queue: of a work area, or of the waiter of a table
     * @param Restaurant $restaurant The restaurant
     * @param WorkStation $station The work area, or waiting for the jobs of a waiter
     * @param float $minutes How much work the job is, in minutes at normal speed
     * @return Work The job
     */
    public function createJob(
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
     * @param Restaurant $restaurant The restaurant
     * @param WorkStation $station The work area
     * @param bool $lock Whether to lock the jobs, as when they are about to be worked on
     * @param bool $withFinished Whether to include the finished jobs that wait for the rest of the queue
     * @return Collection<Work> The jobs
     */
    public function getQueue(Restaurant $restaurant, WorkStation $station, bool $lock = true, bool $withFinished = false): Collection
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
     * @param bool $lock Whether to lock the jobs, as when they are about to be worked on
     * @return Collection<Work> The jobs
     */
    public function getWaitingQueue(array $tableUuids, bool $lock = true): Collection
    {
        $query = Work::where('station', WorkStation::WAITING->value)
            ->whereIn('table_uuid', $tableUuids)
            ->with(['Table', 'Order', 'ServedMeal.Recipe', 'ServedMeal.OrderItem'])
            ->orderBy('order')
            ->orderBy('created_at');

        return $lock ? $query->lockForUpdate()->get() : $query->get();
    }

    /**
     * Clears the finished jobs of a work area once nothing is left to do there, starting its total over
     * @param Restaurant $restaurant The restaurant
     * @param WorkStation $station The work area
     */
    public function clearIfDone(Restaurant $restaurant, WorkStation $station): void
    {
        if ($this->getQueue($restaurant, $station)->isEmpty()) {
            Work::where('restaurant_uuid', $restaurant->uuid)->where('station', $station->value)->delete();
        }
    }

    //| Speeds

    /**
     * Gets the combined speed of the employees working in a work area
     * @param Collection<Employee> $employees The restaurant's employees
     * @param WorkStation $station The work area
     * @return float The combined speed, or 0 when nobody works there
     */
    public function getSpeed(Collection $employees, WorkStation $station): float
    {
        return round($employees->filter(fn (Employee $employee) => $employee->station === $station)
            ->sum(fn (Employee $employee) => $employee->getSpeed($station)), 2);
    }

    /**
     * Gets the employees cleaning, with their speed, fastest first
     * @param Collection<Employee> $employees The restaurant's employees
     * @return array<string, float> The speed of each cleaner, keyed by their uuid
     */
    public function getCleaners(Collection $employees): array
    {
        return $employees
            ->filter(fn (Employee $employee) => $employee->station === WorkStation::CLEANING && $employee->cleaning_speed > 0)
            ->sortBy([['cleaning_speed', 'desc'], ['name', 'asc']])
            ->mapWithKeys(fn (Employee $employee) => [$employee->uuid => $employee->cleaning_speed])
            ->all();
    }

    /**
     * Gets the employees waiting tables rather than working in a work area
     * @param Collection<Employee> $employees The restaurant's employees, with their tables
     * @return Collection<Employee> The waiters
     */
    public function getWaiters(Collection $employees): Collection
    {
        return $employees->filter(fn (Employee $employee) => $employee->station === null && $employee->Tables->isNotEmpty());
    }

    /**
     * Gets how fast a job in the prep area goes at a moment: a meal at the speed its equipment and boosts give it, a
     * conversion at the speed the boosts on the prep area give it
     * @param Work $job The job
     * @param Carbon $at The moment, in the restaurant's time
     * @param BaseCollection $boosts The boosts that may be active
     * @return float The speed, where 1 is normal
     */
    public function getJobSpeed(Work $job, Carbon $at, BaseCollection $boosts): float
    {
        return $job->Meal !== null
            ? $this->boostService->getMealSpeed($job->Meal, EquipmentStage::PREP, $at, $boosts)
            : $this->boostService->getAreaSpeed(Boost::AREA_PREP, $at, $boosts);
    }

    /**
     * Works out how cleaning goes: the fastest cleaners take the first pieces in the queue, and each takes the next
     * piece as soon as theirs is clean. Boosts on the cleaning area make everyone clean faster while they last
     * @param Restaurant $restaurant The restaurant
     * @param Collection<Work> $queue The pieces still to clean, in order
     * @param array<string, float> $cleaners The speed of each cleaner, keyed by their uuid, fastest first
     * @param Carbon $start When cleaning starts, in the restaurant's time
     * @param float|null $limit How many minutes to work out, or null to work out until everything is clean
     * @return array When each finished piece was done in minutes from the start, the work done on the others, and
     *               the piece each cleaner starts on
     */
    public function scheduleCleaning(Restaurant $restaurant, Collection $queue, array $cleaners, Carbon $start, ?float $limit): array
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

    //| Finishing jobs

    /**
     * Does minutes of work straight away on jobs of a work area, as a boost does, finishing those with nothing left.
     * Once the whole area is done, its finished jobs are cleared
     * @param Restaurant $restaurant The restaurant
     * @param WorkStation $station The work area the jobs are in
     * @param BaseCollection<Work> $jobs The jobs
     * @param float $minutes How many minutes of work each job gets
     * @param Carbon $now The restaurant's current time
     */
    public function cutJobs(Restaurant $restaurant, WorkStation $station, BaseCollection $jobs, float $minutes, Carbon $now): void
    {
        foreach ($jobs as $job) {
            $job->progress = round(min($job->work, $job->progress + $minutes), 2);
            $job->isFinished() ? $this->finishJob($restaurant, $job, $now) : $job->save();
        }

        if ($jobs->isNotEmpty()) {
            $this->clearIfDone($restaurant, $station);
        }
    }

    /**
     * Finishes a job: the meal is prepped, the converted ingredient goes in the pile, or the pieces of equipment are
     * clean. The job stays, marked as finished, until the rest of its area's queue is done
     * @param Restaurant $restaurant The restaurant
     * @param Work $job The job
     * @param Carbon $at When it finished, in the restaurant's time
     */
    public function finishJob(Restaurant $restaurant, Work $job, Carbon $at): void
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
     * Finishes a waiter's job: the order is taken, or the portion is served. Unlike the work areas, waiters have no
     * total to keep, so the job is cleared straight away
     * @param Work $job The job
     * @param Carbon $at When it finished, in the restaurant's time
     */
    public function finishWaitingJob(Work $job, Carbon $at): void
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
     * The waiter is done taking an order. An order started without anything on it is then taken, waiting for what was
     * ordered to be noted down, while one placed with its items straight away opens to be served
     * @param Order $order The order
     * @param Carbon $at When they were done, in the restaurant's time
     */
    public function finishTakingOrder(Order $order, Carbon $at): void
    {
        if ($order->status !== Order::STATUS_TAKING) {
            return;
        }

        $order->status = $order->Items()->exists() ? Order::STATUS_OPEN : Order::STATUS_TAKEN;
        // The table has ordered once the waiter has taken it, which is what its waiting is counted from
        $order->ordered_at = $at;
        $order->recalculate($at);
        $order->save();
    }

    /**
     * The waiter arrived with a plated portion, which serves the order entry it was set aside for. The customer rates
     * how satisfied they are with it there and then
     * @param ServedMeal $plate The plated portion
     * @param Carbon $at When they arrived, in the restaurant's time
     */
    public function finishDelivery(ServedMeal $plate, Carbon $at): void
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
}
