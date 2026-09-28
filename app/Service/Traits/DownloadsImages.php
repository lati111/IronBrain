<?php

namespace App\Service\Traits;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

trait DownloadsImages
{
    /** @var Closure(string $failedUrl, string $path): ?string|null Resolves a replacement url when a download fails */
    private ?Closure $missingImageResolver = null;

    /**
     * Sets the callback used to get a replacement url when an image could not be downloaded
     * @param ?Closure $resolver Receives the failed url and target path, and returns a new url to try or null to skip
     */
    public function onMissingImage(?Closure $resolver): void
    {
        $this->missingImageResolver = $resolver;
    }

    /**
     * Downloads an image and saves it to storage, unless it already exists
     * @param string $url The url to download the image from
     * @param string $path The storage path to save the image to
     * @return bool Returns whether the image exists in storage afterwards
     */
    protected function downloadImage(string $url, string $path): bool
    {
        if (Storage::exists($path)) {
            return true;
        }

        while ($url !== null) {
            $contents = $this->fetchImage($url);
            if ($contents !== null) {
                Storage::put($path, $contents);
                return true;
            }

            $url = ($this->missingImageResolver !== null) ? ($this->missingImageResolver)($url, $path) : null;
        }

        return false;
    }

    /**
     * Fetches the contents of an image url
     * @param string $url The url to fetch
     * @return ?string Returns the image contents, or null if the url did not return a valid image
     */
    private function fetchImage(string $url): ?string
    {
        try {
            $response = Http::get($url);
        } catch (ConnectionException) {
            return null;
        }

        $contents = $response->body();
        if ($response->failed() || $contents === '' || @getimagesizefromstring($contents) === false) {
            return null;
        }

        return $contents;
    }
}
