<?php

namespace Tests\Feature\Api\PKSanc\PokedexApi;

use App\Enum\PKSanc\PKSancStrings;
use App\Enum\PKSanc\PokedexMarkings;
use App\Models\Auth\User;
use App\Models\PKSanc\PokedexMarking;
use Tests\Feature\Api\AbstractApiFeatureTester;
use Tests\Traits\ValidationTests;

class SetPokedexMarkingTest extends AbstractApiFeatureTester
{
    use ValidationTests;

    protected function getOperationType(): string
    {
        return 'POST';
    }

    protected function getRoute(): string
    {
        return route('api.pksanc.pokedex.mark');
    }

    protected function getModel(): PokedexMarking
    {
        return new PokedexMarking();
    }

    function getPostParameters(array $overwrites = []): array
    {
        return array_merge([
            'pokedex_id' => fake()->numberBetween(1, 905),
            'form_index' => 0,
            'marking'    => PokedexMarkings::CAUGHT,
        ], $overwrites);
    }

    //| validation tests

    public function test_pokedex_id_validation(): void
    {
        $this->assertRequiredValidation('pokedex_id', 'pokedex id');
        $this->assertIntegerValidation('pokedex_id', 'pokedex id');
    }

    public function test_form_index_validation(): void
    {
        $this->assertRequiredValidation('form_index', 'form index');
        $this->assertIntegerValidation('form_index', 'form index');
    }

    public function test_marking_validation(): void
    {
        $this->assertRequiredValidation('marking');
        $this->assertStringValidation('marking');
        $this->assertInArrayValidation('marking', 'INVALID_MARKING');
    }

    //| set marking tests

    public function test_set_marking(): void
    {
        $user = $this->getAdminUser();
        $params = $this->getPostParameters();

        $response = $this->getHttpClient($user)->post($this->getRoute(), $params);

        $response->assertCreated();
        $this->assertApiMessage(PKSancStrings::POKEDEX_POKEMON_MARKED, $response);

        $this->assertNotNull(
            PokedexMarking::where('pokedex_id', $params['pokedex_id'])
                ->where('form_index', $params['form_index'])
                ->where('marking', $params['marking'])
                ->where('user_uuid', $user->uuid)
                ->first()
        );
    }

    public function test_set_marking_already_marked(): void
    {
        $user = $this->getAdminUser();
        $params = $this->getPostParameters();

        $existing = new PokedexMarking();
        $existing->pokedex_id = $params['pokedex_id'];
        $existing->form_index = $params['form_index'];
        $existing->marking = $params['marking'];
        $existing->user_uuid = $user->uuid;
        $existing->save();

        $response = $this->getHttpClient($user)->post($this->getRoute(), $params);

        $response->assertStatus(208);
        $this->assertApiMessage(PKSancStrings::POKEDEX_POKEMON_MARKED, $response);
    }
}
