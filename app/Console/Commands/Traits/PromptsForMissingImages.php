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
            $this->newLine(2);
            $this->warn(sprintf('Could not download a valid image from "%s" for "%s".', $failedUrl, $path));

            if (!$this->input->isInteractive()) {
                $this->line('Skipping image.');
                return null;
            }

            $url = trim((string) $this->ask('Enter the url to use instead, or leave empty to skip'));
            return ($url !== '') ? $url : null;
        };
    }
}
