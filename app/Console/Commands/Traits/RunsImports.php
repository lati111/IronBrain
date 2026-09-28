<?php

namespace App\Console\Commands\Traits;

use Closure;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Helper\ProgressBar;
use Throwable;

trait RunsImports
{
    /** @var bool Whether any of the imports failed */
    private bool $importFailed = false;

    /** @var array<array{string, string}> The result of each import, used for the summary table */
    private array $importResults = [];

    /** @var ?ProgressBar The progress bar currently being shown, if any */
    private ?ProgressBar $activeProgressBar = null;

    /**
     * Imports a collection of entries inside a transaction, so a failure never leaves a partial import behind.
     * A failing import is reported and rolled back, after which the command continues with the next import.
     * @param string $label The plural name of what is being imported, eg. "moves"
     * @param Closure(): array $fetch Fetches the entries to import
     * @param Closure(mixed $data, mixed $key): bool $import Imports a single entry, and returns whether anything changed
     * @param ?Closure(): int $finish Runs after all entries are imported, eg. to remove stale entries, and returns the amount of changes
     * @param ?Closure(mixed $data, mixed $key): string $describe Gets the name of an entry, shown next to the progress bar while it is imported
     * @return bool Returns whether the import succeeded
     */
    protected function runImport(string $label, Closure $fetch, Closure $import, ?Closure $finish = null, ?Closure $describe = null): bool
    {
        $this->line(sprintf('Importing %s...', $label));

        try {
            $collection = $fetch();

            $bar = $this->startProgressBar(count($collection));

            $changedCount = DB::transaction(function () use ($collection, $import, $finish, $describe, $bar) {
                $changedCount = 0;
                foreach ($collection as $key => $data) {
                    if ($describe !== null) {
                        $bar->setMessage($describe($data, $key));
                        $bar->display();
                    }

                    if ($import($data, $key)) {
                        $changedCount++;
                    }

                    $bar->advance();
                }

                if ($finish !== null) {
                    $bar->setMessage('Removing stale entries...');
                    $bar->display();
                    $changedCount += $finish();
                }

                return $changedCount;
            });

            $bar->setMessage('');
            $this->finishProgressBar();
            $this->importResults[] = [ucfirst($label), sprintf('%d changed', $changedCount)];
            return true;
        } catch (Throwable $e) {
            report($e);

            $this->activeProgressBar = null;
            $this->newLine();
            $this->error(sprintf('Failed to import %s: %s', $label, $e->getMessage()));
            $this->line(sprintf('All changes to %s have been rolled back.', $label));

            $this->importFailed = true;
            $this->importResults[] = [ucfirst($label), 'failed'];
            return false;
        }
    }

    /**
     * Creates a callback that shows the progress of a download in a progress bar
     * @param string $label What is being downloaded, eg. "sprites"
     * @return Closure(int $done, int $total): void The callback to pass to the downloader
     */
    protected function downloadProgress(string $label): Closure
    {
        return function (int $done, int $total) use ($label) {
            if ($this->activeProgressBar === null) {
                if ($total === 0) {
                    $this->line(sprintf('All %s have already been downloaded.', $label));
                    return;
                }

                $this->line(sprintf('Downloading %d %s...', $total, $label));
                $this->startProgressBar($total);
            }

            $this->activeProgressBar->setProgress($done);
            if ($done >= $total) {
                $this->finishProgressBar();
            }
        };
    }

    /**
     * Runs a callback that writes to the console while a progress bar is shown, eg. to ask a question.
     * The progress bar is hidden while the callback runs, so the output does not get mixed up.
     * @template T
     * @param Closure(): T $callback The callback to run
     * @return T Returns the result of the callback
     */
    protected function withoutProgressBar(Closure $callback): mixed
    {
        $bar = $this->activeProgressBar;
        if ($bar === null) {
            return $callback();
        }

        $bar->clear();
        try {
            return $callback();
        } finally {
            $bar->display();
        }
    }

    /**
     * Starts a new progress bar in the style used by the imports
     * @param int $max The amount of steps in the progress bar
     * @return ProgressBar Returns the progress bar
     */
    private function startProgressBar(int $max): ProgressBar
    {
        $bar = $this->output->createProgressBar($max);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %message%');
        $bar->setMessage('');
        $bar->start();

        $this->activeProgressBar = $bar;
        return $bar;
    }

    /**
     * Finishes the progress bar currently being shown
     */
    private function finishProgressBar(): void
    {
        $this->activeProgressBar?->finish();
        $this->activeProgressBar = null;
        $this->newLine();
    }

    /**
     * Marks the command as failed without running an import, eg. when a dependency failed
     * @param string $label The plural name of what was not imported
     * @param string $reason Why the import was skipped
     */
    protected function skipImport(string $label, string $reason): void
    {
        $this->warn(sprintf('Skipping %s: %s', $label, $reason));

        $this->importFailed = true;
        $this->importResults[] = [ucfirst($label), 'skipped'];
    }

    /**
     * Prints the summary of all imports
     * @param string $name The name of what was imported, eg. "PKSanc"
     * @return int Returns the exit code for the command
     */
    protected function finishImport(string $name): int
    {
        if ($this->importFailed) {
            $this->error(sprintf('%s import finished with errors.', $name));
        } else {
            $this->info(sprintf('%s import successful!', $name));
        }

        $this->table(['', ''], $this->importResults);
        $exitCode = $this->importFailed ? self::FAILURE : self::SUCCESS;

        // Commands can be called multiple times in the same process, eg. from import:all
        $this->importFailed = false;
        $this->importResults = [];

        return $exitCode;
    }
}
