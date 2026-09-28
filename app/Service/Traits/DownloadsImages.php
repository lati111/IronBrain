<?php

namespace App\Service\Traits;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

trait DownloadsImages
{
    /** @var int The amount of images downloaded at the same time when prefetching */
    private int $imageConcurrency = 16;

    /** @var Closure(string $failedUrl, string $path): ?string|null Resolves a replacement url when a download fails */
    private ?Closure $missingImageResolver = null;

    /** @var bool Whether images that already exist in storage should be downloaded again */
    private bool $refreshImages = false;

    /** @var array<string, true> The storage paths of the images downloaded during this run */
    private array $downloadedImages = [];

    /**
     * Sets the callback used to get a replacement url when an image could not be downloaded
     * @param ?Closure $resolver Receives the failed url and target path, and returns a new url to try or null to skip
     */
    public function onMissingImage(?Closure $resolver): void
    {
        $this->missingImageResolver = $resolver;
    }

    /**
     * Sets whether images that already exist in storage should be downloaded again
     * @param bool $refresh Whether to refresh existing images
     */
    public function setRefreshImages(bool $refresh): void
    {
        $this->refreshImages = $refresh;
    }

    /**
     * Downloads an image and saves it to storage, unless it already exists
     * @param string $url The url to download the image from
     * @param string $path The storage path to save the image to
     * @return bool Returns whether the image exists in storage afterwards
     */
    protected function downloadImage(string $url, string $path): bool
    {
        return $this->downloadFirstImage([$url], $path) !== null;
    }

    /**
     * Downloads the first valid image from a list of urls and saves it to storage, unless it already exists.
     * The missing image resolver is only asked for a replacement once all urls have failed.
     * @param array<?string> $urls The urls to try, in order of preference. Null values are ignored
     * @param string $path The storage path to save the image to
     * @param bool $resolveMissing Whether to ask the missing image resolver for a replacement when all urls fail
     * @return ?string Returns the url the image was downloaded from, the first url if the image already existed, or null if no image could be saved
     */
    protected function downloadFirstImage(array $urls, string $path, bool $resolveMissing = true): ?string
    {
        $urls = array_values(array_unique(array_filter($urls)));
        if ($urls === []) {
            return null;
        }

        if (!$this->shouldDownload($path)) {
            return $urls[0];
        }

        foreach ($urls as $url) {
            if ($this->saveImage($url, $path)) {
                return $url;
            }
        }

        // Keep the current image when a refresh fails
        if (Storage::exists($path)) {
            return $urls[0];
        }

        $failedUrl = end($urls);
        while ($resolveMissing && $this->missingImageResolver !== null) {
            $url = ($this->missingImageResolver)($failedUrl, $path);
            if ($url === null) {
                break;
            }

            if ($this->saveImage($url, $path)) {
                return $url;
            }

            $failedUrl = $url;
        }

        return null;
    }

    /**
     * Downloads multiple images in parallel, so they don't have to be downloaded one by one later.
     * Failed downloads are ignored, and are handled when the image is downloaded normally.
     * @param array<string, ?string> $urls The urls to download, keyed by the storage path to save them to
     * @param ?Closure(int $done, int $total): void $onProgress Called before the first download and after every batch of downloads
     */
    protected function prefetchImages(array $urls, ?Closure $onProgress = null): void
    {
        $urls = array_filter($urls, fn (?string $url, string $path) => $url !== null && $this->shouldDownload($path), ARRAY_FILTER_USE_BOTH);

        $total = count($urls);
        $done = 0;
        if ($onProgress !== null) {
            $onProgress($done, $total);
        }

        // Download in batches, so progress can be reported while downloading
        foreach (array_chunk($urls, $this->imageConcurrency * 4, true) as $batch) {
            $responses = Http::pool(function (Pool $pool) use ($batch) {
                foreach ($batch as $path => $url) {
                    $pool->as($path)->get($url);
                }
            }, $this->imageConcurrency);

            foreach ($responses as $path => $response) {
                $contents = ($response instanceof Response) ? $this->validateImage($response) : null;
                if ($contents !== null) {
                    Storage::put($path, $contents);
                    $this->downloadedImages[$path] = true;
                }
            }

            $done += count($batch);
            if ($onProgress !== null) {
                $onProgress($done, $total);
            }
        }
    }

    /**
     * Checks whether an image still needs to be downloaded
     * @param string $path The storage path of the image
     * @return bool Returns whether the image should be downloaded
     */
    private function shouldDownload(string $path): bool
    {
        if (isset($this->downloadedImages[$path])) {
            return false;
        }

        return $this->refreshImages || Storage::missing($path);
    }

    /**
     * Downloads an image and saves it to storage
     * @param string $url The url to download the image from
     * @param string $path The storage path to save the image to
     * @return bool Returns whether a valid image was saved
     */
    private function saveImage(string $url, string $path): bool
    {
        try {
            $contents = $this->validateImage(Http::get($url));
        } catch (ConnectionException) {
            return false;
        }

        if ($contents === null) {
            return false;
        }

        Storage::put($path, $contents);
        $this->downloadedImages[$path] = true;
        return true;
    }

    /**
     * Gets the contents of a response if it contains a valid image
     * @param Response $response The response to validate
     * @return ?string Returns the image contents, or null if the response did not contain a valid image
     */
    private function validateImage(Response $response): ?string
    {
        $contents = $response->body();
        if ($response->failed() || $contents === '' || @getimagesizefromstring($contents) === false) {
            return null;
        }

        return $contents;
    }
}
