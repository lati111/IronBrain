<?php

namespace App\Console\Commands\Traits;

use Closure;

trait PromptsForMissingImages
{
    /**
     * Creates a resolver that asks the user for a replacement url when an image could not be downloaded
     * @return Closure(string $failedUrl, string $path): ?string The resolver
     */
    protected function missingImagePrompt(): Closure
    {
        return function (string $failedUrl, string $path): ?string {
            $prompt = function () use ($failedUrl, $path): ?string {
                $this->warn(sprintf('Could not download a valid image from "%s" for "%s".', $failedUrl, $path));

                if (!$this->input->isInteractive()) {
                    $this->line('Skipping image.');
                    return null;
                }

                $url = trim((string) $this->ask('Enter the url to use instead, or leave empty to skip'));
                return ($url !== '') ? $url : null;
            };

            // Hide the progress bar while prompting, so it does not get mixed up with the question
            return method_exists($this, 'withoutProgressBar') ? $this->withoutProgressBar($prompt) : $prompt();
        };
    }
}
