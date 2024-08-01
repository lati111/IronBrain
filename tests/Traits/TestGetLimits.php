<?php

namespace Tests\Traits;

trait TestGetLimits
{
    /**
     * Tests the offset functionality within a getall request
     * @return void
     */
    public function test_get_all_offset(): void
    {
        $this->createRandomEntities($this->getModel()::class, 5);

        $url = sprintf('%s?offset=1', $this->getRoute());

        $response = $this->getHttpClient($this->getOperationUser())
            ->get($url, $this->getDefaultHeaders());

        $response->assertOk();
        $this->assertCount(4, $response->json()['data']);
    }

    /**
     * Tests the amount functionality within a getall request
     * @return void
     */
    public function test_get_all_amount(): void
    {
        $this->createRandomEntities($this->getModel()::class, 5);

        $url = sprintf('%s?amount=2', $this->getRoute());

        $response = $this->getHttpClient($this->getOperationUser())
            ->get($url, $this->getDefaultHeaders());

        $response->assertOk();
        $this->assertCount(2, $response->json()['data']);
    }

    /**
     * Tests the offset and amount functionality within a getall request
     * @return void
     */
    public function test_get_all_offset_and_amount(): void
    {
        $this->createRandomEntities($this->getModel()::class, 5);

        $url = sprintf('%s?offset=1&amount=3', $this->getRoute());

        $response = $this->getHttpClient($this->getOperationUser())
            ->get($url, $this->getDefaultHeaders());

        $response->assertOk();
        $this->assertCount(3, $response->json()['data']);
    }
}
