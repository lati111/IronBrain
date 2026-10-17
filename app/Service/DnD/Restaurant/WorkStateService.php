<?php

namespace App\Service\DnD\Restaurant;

use App\Enum\DnD\Restaurant\WorkStation;
use App\Models\DnD\Restaurant\Employee;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\Work;
use Illuminate\Database\Eloquent\Collection;

/**
 * Describes the staff and the work areas as shown during service: who is doing what, and how far along each queue is
 */
class WorkStateService
{
    public function __construct(
        private readonly WorkQueueService $workQueueService,
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
            $speed = $this->workQueueService->getSpeed($employees, $station);
            $all = $this->workQueueService->getQueue($restaurant, $station, lock: false, withFinished: true);
            $queue = $all->filter(fn (Work $job) => !$job->isFinished())->values();
            $totalWork = $all->sum('work');

            $tasks[$station->value] = $queue->isNotEmpty()
                ? $this->describeTask($queue->first())
                : $station->label() . ' (idle)';

            // When each job is done, in minutes from now, or null while nobody works in the area
            $finishedAt = [];
            if ($station === WorkStation::CLEANING) {
                $schedule = $this->workQueueService->scheduleCleaning($restaurant, $queue, $this->workQueueService->getCleaners($employees), $restaurant->getNow(), null);
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
                    $before += $job->getRemaining() / $this->workQueueService->getJobSpeed($job, $now, $boosts);
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
                    'progress' => $this->getProgress($job),
                    'minutes_left' => isset($finishedAt[$job->uuid]) ? Minutes::display($finishedAt[$job->uuid]) : null,
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
     * @param Restaurant $restaurant The restaurant
     * @param Collection<Employee> $employees The restaurant's employees, with their tables
     * @return array The jobs, and the job each waiter is on, keyed by their uuid
     */
    private function describeWaiting(Restaurant $restaurant, Collection $employees): array
    {
        $jobs = [];
        $tasks = [];
        $waited = [];

        foreach ($this->workQueueService->getWaiters($employees) as $waiter) {
            $before = 0.0;
            $queue = $this->workQueueService->getWaitingQueue($waiter->Tables->pluck('uuid')->all(), lock: false);
            foreach ($queue as $job) {
                $before += $job->getRemaining();
                $jobs[] = $this->describeWaitingJob($job, Minutes::display($before));
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

    /**
     * Describes a waiter's job, such as taking an order or bringing a plated portion
     * @param Work $job The job
     * @param int|null $minutesLeft How many minutes are left until it is done, or null when nobody waits its table
     * @return array The job
     */
    private function describeWaitingJob(Work $job, ?int $minutesLeft): array
    {
        return [
            'uuid' => $job->uuid,
            'table_uuid' => $job->table_uuid,
            'order_uuid' => $job->order_uuid,
            'served_meal_uuid' => $job->served_meal_uuid,
            'task' => $this->describeTask($job),
            'progress' => $this->getProgress($job),
            'minutes_left' => $minutesLeft,
        ];
    }

    /**
     * Describes what an employee is doing: the job of their work area, the job they are on as a waiter, the tables
     * they wait, or nothing
     * @param Employee $employee The employee
     * @param array<string, string> $tasks The job of each work area, keyed by its code
     * @param array<string, string> $employeeTasks The job of employees working on their own, such as cleaners and
     *                                             waiters, keyed by their uuid
     * @return string What they are doing
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
     * @param Work $job The job
     * @return string The description
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

    /**
     * Gets how far along a job is, from 0 to 1
     * @param Work $job The job
     * @return int|float The progress
     */
    private function getProgress(Work $job): int|float
    {
        return $job->work > 0 ? round($job->progress / $job->work, 4) : 0;
    }
}
