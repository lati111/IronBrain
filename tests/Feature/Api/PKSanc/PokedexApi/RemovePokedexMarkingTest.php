<?php

namespace Tests\Feature\Api\PKSanc\PokedexApi;

use App\Enum\PKSanc\PKSancStrings;
use App\Enum\PKSanc\PokedexMarkings;
use App\Models\Auth\User;
use App\Models\PKSanc\PokedexMarking;
use Tests\Feature\Api\AbstractApiFeatureTester;
use Tests\Traits\ValidationTests;

class RemovePokedexMarkingTest extends AbstractApiFeatureTester
{
    use ValidationTests;

    protected function getOperationType(): string
    {
        return 'POST';
    }

    protected function getRoute(): string
    {
        return route('api.pksanc.pokedex.unmark');
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

    //| remove marking tests

    public function test_remove_marking(): void
    {
        $user = $this->getAdminUser();
        $params = $this->getPostParameters();

        $existing = new PokedexMarking();
        $existing->pokedex_id = $params['pokedex_id'];
        $existing->form_index = $params['form_index'];
        $existing->marking = $params['marking'];
        $existing->user_uuid = $user->uuid;
        $existing->save();
        $uuid = $existing->uuid;

        $response = $this->getHttpClient($user)->post($this->getRoute(), $params);

        $response->assertCreated();
        $this->assertApiMessage(PKSancStrings::POKEDEX_POKEMON_UNMARKED, $response);
        $this->assertNull(PokedexMarking::find($uuid));
    }

    public function test_remove_marking_not_found(): void
    {
        $user = $this->getAdminUser();
        $params = $this->getPostParameters();

        $response = $this->getHttpClient($user)->post($this->getRoute(), $params);

        $response->assertStatus(208);
        $this->assertApiMessage(PKSancStrings::POKEDEX_POKEMON_UNMARKED, $response);
    }
}
