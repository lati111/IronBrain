<?php

namespace Tests\Traits;

use App\Models\AbstractModel;
use Carbon\Carbon;
use Illuminate\Testing\TestResponse;

trait TestInactiveLimit
{
    /** @var bool Whether or not the inactive items can be included for this operation */
    public bool $canIncludeInactive = true;

    /**
     * Tests if hidden items are hidden by default
     * @return void
     */
    public function test_inactive_hidden_by_default(): void
    {
        switch($this->getOperationType()) {
            case "GET_ALL":
                $this->inactiveHiddenTestGetall();
                break;
            case "GET":
                $this->inactiveHiddenTestGet();
                break;
            case "POST_EDIT":
                $this->inactiveHiddenTestPost();
                break;
        }
    }

    /**
     * Tests if hidden items can be shown
     * @return void
     */
    public function test_include_inactive(): void
    {
        switch($this->getOperationType()) {
            case "GET_ALL":
                $this->inactiveShownTestGetall();
                break;
            case "GET":
                $this->inactiveShownTestGet();
                break;
            case "POST_EDIT":
                $this->inactiveShownTestPost();
                break;
        }
    }

    //| GET_ALL
    protected function inactiveHiddenTestGetall(): void
    {
        $this->createRandomEntities($this->getModel()::class, 3, ['deleted_at' => Carbon::now()]);
        $this->createRandomEntities($this->getModel()::class, 2);

        $response = $this->getHttpClient($this->getOperationUser())
            ->get($this->getRoute(), $this->getDefaultHeaders());

        $response->assertOk();
        $this->assertCount(2, $response->json()['data']);
    }

    protected function inactiveShownTestGetall(): void
    {
        $this->createRandomEntities($this->getModel()::class, 3, ['deleted_at' => Carbon::now()]);
        $this->createRandomEntities($this->getModel()::class, 2);

        $url = sprintf('%s?include_inactive=1', $this->getRoute());

        $response = $this->getHttpClient($this->getOperationUser())
            ->get($url, $this->getDefaultHeaders());

        $response->assertOk();
        $this->assertCount($this->canIncludeInactive ? 5 : 2, $response->json()['data']);
    }

    //| GET
    protected function inactiveHiddenTestGet(): void
    {
        /** @var AbstractModel $item */
        $item = $this->createRandomEntities($this->getModel()::class, 1, ['deleted_at' => Carbon::now()])->first();

        $response = $this->getHttpClient($this->getOperationUser())
            ->get($this->getRoute($item), $this->getDefaultHeaders());

        $this->assertInvalid($response);
    }

    protected function inactiveShownTestGet(): void
    {
        /** @var AbstractModel $item */
        $item = $this->createRandomEntities($this->getModel()::class, 1, ['deleted_at' => Carbon::now()])->first();

        $url = sprintf('%s?include_inactive=1', $this->getRoute($item));

        $response = $this->getHttpClient($this->getOperationUser())
            ->get($url, $this->getDefaultHeaders());

        if ($this->canIncludeInactive === false) {
            $this->assertInvalid($response);
            return;
        }

        $response->assertOk();
        $this->assertArrayEquals($item->toArray(), $response->json()['data']);
    }

    //| POST
    protected function inactiveHiddenTestPost(): void
    {
        /** @var AbstractModel $item */
        $item = $this->createRandomEntities($this->getModel()::class, 1, ['deleted_at' => Carbon::now()])->first();

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute($item), $this->getPostParameters(), $this->getDefaultHeaders());

        $this->assertInvalid($response);
    }

    protected function inactiveShownTestPost(): void
    {
        /** @var AbstractModel $item */
        $item = $this->createRandomEntities($this->getModel()::class, 1, ['deleted_at' => Carbon::now()])->first();

        $url = sprintf('%s?include_inactive=1', $this->getRoute($item));

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($url, $this->getPostParameters(), $this->getDefaultHeaders());

        if ($this->canIncludeInactive === false) {
            $this->assertInvalid($response);
            return;
        }

        $response->assertOk();
    }

    //| Utils
    protected function assertInvalid(TestResponse $response): void {
        $response->assertNotFound();
        $this->assertEquals($this->getNotFoundMessage(), $response->json()['message']);
    }
}
