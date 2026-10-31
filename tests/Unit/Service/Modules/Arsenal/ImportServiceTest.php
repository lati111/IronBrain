<?php

namespace Tests\Unit\Service\Modules\Arsenal;

use App\Models\Arsenal\Weapon;
use App\Service\Arsenal\ImportService;
use Tests\Traits\Arsenal\ArsenalTestHelper;
use Tests\Unit\Service\AbstractServiceTester;

class ImportServiceTest extends AbstractServiceTester
{
    use ArsenalTestHelper;

    private ImportService $importer;

    public function setUp(): void
    {
        parent::setUp();
        $this->importer = new ImportService();
    }

    //| weapon type tests

    public function test_import_weapon_maps_exalted_weapon_type(): void
    {
        $this->importer->importWeapon($this->weaponData([
            'uniqueName' => '/Test/Powersuits/ExaltedSword',
            'type'       => 'Exalted Weapon',
            'category'   => 'Misc',
        ]));

        $this->assertSame('exalted', Weapon::where('id', '/Test/Powersuits/ExaltedSword')->first()->type);
    }

    public function test_import_weapon_maps_companion_weapon_type(): void
    {
        $this->importer->importWeapon($this->weaponData([
            'uniqueName' => '/Test/Sentinels/SentinelGun',
            'type'       => 'Companion Weapon',
            'category'   => 'Primary',
        ]));

        $this->assertSame('companion_weapon', Weapon::where('id', '/Test/Sentinels/SentinelGun')->first()->type);
    }

    //| exalted link tests

    public function test_import_warframe_links_exalted_weapons(): void
    {
        $weapon = $this->createTestWeapon('/Test/Powersuits/ExaltedSword', 'exalted');

        $this->importer->importWarframe([
            'uniqueName' => '/Test/Powersuits/TestFrame',
            'name'       => 'Test Frame',
            'isPrime'    => false,
            'exalted'    => [$weapon->id, '/Test/Powersuits/Unknown'],
        ]);

        $this->assertSame('/Test/Powersuits/TestFrame', $weapon->fresh()->exalted_id);
    }

    private function weaponData(array $overwrites): array
    {
        return array_merge([
            'name'        => 'Test Weapon',
            'description' => 'A test weapon',
            'isPrime'     => false,
        ], $overwrites);
    }
}
