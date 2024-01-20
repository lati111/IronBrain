<?php

namespace Tests\Unit\Controller\Modules\PKSanc;

use Tests\Unit\Controller\AbstractControllerUnitTester;

class PKSancControllerTest extends AbstractControllerUnitTester
{
    //| show overview test
    /**
     * Test if controller returns proper view
     * @return void
     */
    public function testOverviewShow(): void
    {
        $response = $this
            ->actingAs($this->getAdminUser())
            ->get(route('pksanc.home.show'));
        $this->assertView($response, 'modules.pksanc.home');
    }
}
