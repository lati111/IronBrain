<?php

namespace Tests\Unit\Service;

use App\Service\Traits\DownloadsImages;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class DownloadsImagesTest extends AbstractServiceTester
{
    private const string validUrl = 'https://example.com/valid.png';
    private const string missingUrl = 'https://example.com/missing.png';
    private const string invalidUrl = 'https://example.com/invalid.png';
    private const string path = 'modules/test/image.png';

    private object $downloader;

    public function setUp(): void
    {
        parent::setUp();
        Storage::fake();

        // 1x1 transparent png
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
        Http::fake([
            self::validUrl => Http::response($png),
            self::missingUrl => Http::response('Not Found', 404),
            self::invalidUrl => Http::response('not an image'),
        ]);

        $this->downloader = new class {
            use DownloadsImages;

            public function download(string $url, string $path): bool
            {
                return $this->downloadImage($url, $path);
            }

            public function downloadFirst(array $urls, string $path, bool $resolveMissing = true): ?string
            {
                return $this->downloadFirstImage($urls, $path, $resolveMissing);
            }

            public function prefetch(array $urls, ?\Closure $onProgress = null): void
            {
                $this->prefetchImages($urls, $onProgress);
            }
        };
    }

    public function testDownloadValid(): void
    {
        $this->assertTrue($this->downloader->download(self::validUrl, self::path));
        Storage::assertExists(self::path);
    }

    public function testDownloadMissingWithoutResolver(): void
    {
        $this->assertFalse($this->downloader->download(self::missingUrl, self::path));
        Storage::assertMissing(self::path);
    }

    public function testDownloadInvalidImageWithoutResolver(): void
    {
        $this->assertFalse($this->downloader->download(self::invalidUrl, self::path));
        Storage::assertMissing(self::path);
    }

    public function testDownloadMissingUsesResolvedUrl(): void
    {
        $failedUrls = [];
        $this->downloader->onMissingImage(function (string $failedUrl, string $path) use (&$failedUrls) {
            $failedUrls[] = $failedUrl;
            return count($failedUrls) === 1 ? self::invalidUrl : self::validUrl;
        });

        $this->assertTrue($this->downloader->download(self::missingUrl, self::path));
        $this->assertEquals([self::missingUrl, self::invalidUrl], $failedUrls);
        Storage::assertExists(self::path);
    }

    public function testDownloadMissingResolverSkips(): void
    {
        $this->downloader->onMissingImage(fn () => null);

        $this->assertFalse($this->downloader->download(self::missingUrl, self::path));
        Storage::assertMissing(self::path);
    }

    public function testDownloadFirstUsesFallbackUrl(): void
    {
        $resolverCalled = false;
        $this->downloader->onMissingImage(function () use (&$resolverCalled) {
            $resolverCalled = true;
            return null;
        });

        $url = $this->downloader->downloadFirst([null, self::missingUrl, self::invalidUrl, self::validUrl], self::path);
        $this->assertEquals(self::validUrl, $url);
        $this->assertFalse($resolverCalled);
        Storage::assertExists(self::path);
    }

    public function testDownloadFirstPromptsOnceAllUrlsFail(): void
    {
        $failedUrls = [];
        $this->downloader->onMissingImage(function (string $failedUrl) use (&$failedUrls) {
            $failedUrls[] = $failedUrl;
            return self::validUrl;
        });

        $url = $this->downloader->downloadFirst([self::missingUrl, self::invalidUrl], self::path);
        $this->assertEquals(self::validUrl, $url);
        $this->assertEquals([self::invalidUrl], $failedUrls);
    }

    public function testDownloadFirstWithoutResolving(): void
    {
        $resolverCalled = false;
        $this->downloader->onMissingImage(function () use (&$resolverCalled) {
            $resolverCalled = true;
            return self::validUrl;
        });

        $this->assertNull($this->downloader->downloadFirst([self::missingUrl], self::path, false));
        $this->assertFalse($resolverCalled);
        Storage::assertMissing(self::path);
    }

    public function testDownloadFirstWithoutUrls(): void
    {
        $this->assertNull($this->downloader->downloadFirst([null, null], self::path));
        Http::assertNothingSent();
    }

    public function testPrefetchDownloadsValidImages(): void
    {
        $this->downloader->prefetch([
            'modules/test/valid.png' => self::validUrl,
            'modules/test/missing.png' => self::missingUrl,
            'modules/test/none.png' => null,
        ]);

        Storage::assertExists('modules/test/valid.png');
        Storage::assertMissing('modules/test/missing.png');
        Storage::assertMissing('modules/test/none.png');
    }

    public function testPrefetchReportsProgress(): void
    {
        // More images than fit in a single batch
        $urls = [];
        for ($i = 0; $i < 100; $i++) {
            $urls[sprintf('modules/test/%d.png', $i)] = self::validUrl;
        }
        Storage::put('modules/test/0.png', 'existing');

        $progress = [];
        $this->downloader->prefetch($urls, function (int $done, int $total) use (&$progress) {
            $progress[] = [$done, $total];
        });

        // Existing images are not counted
        $this->assertEquals([[0, 99], [64, 99], [99, 99]], $progress);
    }

    public function testPrefetchReportsProgressWhenNothingToDownload(): void
    {
        Storage::put(self::path, 'existing');

        $progress = [];
        $this->downloader->prefetch([self::path => self::validUrl], function (int $done, int $total) use (&$progress) {
            $progress[] = [$done, $total];
        });

        $this->assertEquals([[0, 0]], $progress);
        Http::assertNothingSent();
    }

    public function testPrefetchedImageIsNotDownloadedAgain(): void
    {
        $this->downloader->setRefreshImages(true);
        $this->downloader->prefetch([self::path => self::validUrl]);
        Http::assertSentCount(1);

        $this->assertTrue($this->downloader->download(self::validUrl, self::path));
        Http::assertSentCount(1);
    }

    public function testRefreshReplacesExistingImage(): void
    {
        Storage::put(self::path, 'existing');
        $this->downloader->setRefreshImages(true);

        $this->assertTrue($this->downloader->download(self::validUrl, self::path));
        $this->assertNotEquals('existing', Storage::get(self::path));
    }

    public function testFailedRefreshKeepsExistingImage(): void
    {
        Storage::put(self::path, 'existing');
        $this->downloader->setRefreshImages(true);
        $this->downloader->onMissingImage(fn () => $this->fail('Should not prompt when an image already exists'));

        $this->assertTrue($this->downloader->download(self::missingUrl, self::path));
        $this->assertEquals('existing', Storage::get(self::path));
    }

    public function testDownloadSkipsExistingImage(): void
    {
        Storage::put(self::path, 'existing');

        $this->assertTrue($this->downloader->download(self::missingUrl, self::path));
        Http::assertNothingSent();
    }
}
