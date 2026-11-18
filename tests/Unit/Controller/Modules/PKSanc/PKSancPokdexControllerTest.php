<?php

namespace Tests\Unit\Controller\Modules\PKSanc;

use Tests\Unit\Controller\AbstractControllerUnitTester;

class PKSancPokdexControllerTest extends AbstractControllerUnitTester
{
    //| show pokedex test
    /**
     * Test if controller returns proper view
     * @return void
     */
    public function testPokedexShow(): void
    {
        $response = $this
            ->actingAs($this->getAdminUser())
            ->get(route('pksanc.pokedex.show'));
        $this->assertView($response, 'modules.pksanc.pokedex', ['perpageoptions']);
    }
}
