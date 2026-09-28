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

    public function testDownloadSkipsExistingImage(): void
    {
        Storage::put(self::path, 'existing');

        $this->assertTrue($this->downloader->download(self::missingUrl, self::path));
        Http::assertNothingSent();
    }
}
