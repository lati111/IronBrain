<?php

namespace Tests\Unit\Controller\Modules\Arsenal;

use Tests\Unit\Controller\AbstractControllerUnitTester;

class ArsenalControllerTest extends AbstractControllerUnitTester
{
    //| show overview test
    public function testOverviewShow(): void
    {
        $response = $this
            ->actingAs($this->getAdminUser())
            ->get(route('arsenal.home.show'));
        $this->assertView($response, 'modules.arsenal.home');
    }

    //| show armory test
    public function testArmoryShow(): void
    {
        $response = $this
            ->actingAs($this->getAdminUser())
            ->get(route('arsenal.armory.show'));
        $this->assertView($response, 'modules.arsenal.armory');
    }

    //| show foundry test
    public function testFoundryShow(): void
    {
        $response = $this
            ->actingAs($this->getAdminUser())
            ->get(route('arsenal.foundry.show'));
        $this->assertView($response, 'modules.arsenal.foundry');
    }
}
