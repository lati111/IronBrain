<?php

namespace Tests\Feature\Api\PKSanc\DepositApi;

use App\Enum\PKSanc\PKSancStrings;
use App\Models\PKSanc\ImportCsv;
use App\Service\PKSanc\DepositService;
use Illuminate\Support\Str;
use Mockery;
use Mockery\MockInterface;
use Tests\Feature\Api\AbstractApiFeatureTester;
use Tests\Traits\PKSanc\PKSancTestHelper;

class ConfirmDepositTest extends AbstractApiFeatureTester
{
    use PKSancTestHelper;

    protected function getOperationType(): string
    {
        return 'POST';
    }

    protected function getRoute(): string
    {
        return route('api.pksanc.deposit.stage.confirm', [Str::uuid()]);
    }

    protected function getModel(): ImportCsv
    {
        return new ImportCsv();
    }

    function getPostParameters(array $overwrites = []): array
    {
        return array_merge([], $overwrites);
    }

    //| csv not found test

    public function test_csv_not_found(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())
            ->post(route('api.pksanc.deposit.stage.confirm', [Str::uuid()]));

        $response->assertNotFound();
        $this->assertApiMessage(PKSancStrings::CSV_NOT_FOUND, $response);
    }

    //| confirm deposit test

    public function test_confirm_deposit(): void
    {
        $user = $this->getAdminUser();
        $game = $this->createTestGame();
        $csv = $this->createTestImportCsv($user->uuid, $game->game);

        $this->instance(
            DepositService::class,
            Mockery::mock(DepositService::class, function (MockInterface $mock) {
                $mock->shouldReceive('confirmStaging')->never();
            })
        );

        $response = $this->getHttpClient($user)
            ->post(route('api.pksanc.deposit.stage.confirm', [$csv->uuid]));

        $response->assertOk();
        $this->assertEquals('confirmed', $response->json('message'));

        $csv->refresh();
        $this->assertTrue((bool) $csv->validated);
    }
}
