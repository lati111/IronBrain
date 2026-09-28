<?php

namespace Tests\Unit\Service\Modules\Arsenal;

use App\Models\Arsenal\Component;
use App\Models\Arsenal\Weapon;
use App\Service\Arsenal\ImportService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Traits\Arsenal\ArsenalTestHelper;
use Tests\Unit\Service\AbstractServiceTester;

class ImportServiceTest extends AbstractServiceTester
{
    use ArsenalTestHelper;

    private const string weaponId = '/Lotus/Weapons/ImportTest/TestRifle';

    private ImportService $importer;

    public function setUp(): void
    {
        parent::setUp();
        Storage::fake();
        Http::fake(['*' => Http::response('Not Found', 404)]);

        $this->importer = new ImportService();
    }

    private function componentData(string $name, int $itemCount = 1): array
    {
        return [
            'uniqueName' => '/Lotus/Types/Recipes/ImportTest/' . $name,
            'name' => $name,
            'itemCount' => $itemCount,
            'type' => 'Component',
        ];
    }

    private function weaponData(array $components, array $overrides = []): array
    {
        return array_replace([
            'uniqueName' => self::weaponId,
            'name' => 'Import Test Rifle',
            'type' => 'Rifle',
            'category' => 'Primary',
            'isPrime' => false,
            'components' => $components,
        ], $overrides);
    }

    private function getTestComponents()
    {
        return Component::where('blueprint_id', self::weaponId)->orderBy('id')->get();
    }

    //| change counting

    public function testChangeToEarlierComponentIsCounted(): void
    {
        $this->importer->importWeapon($this->weaponData([$this->componentData('Barrel'), $this->componentData('Stock')]));

        // Only the first component changes
        $changed = (new ImportService())->importWeapon($this->weaponData([$this->componentData('Barrel', 2), $this->componentData('Stock')]));

        $this->assertTrue($changed);
    }

    public function testUnchangedWeaponIsNotCounted(): void
    {
        $data = $this->weaponData([$this->componentData('Barrel'), $this->componentData('Stock')]);
        $this->importer->importWeapon($data);

        $this->assertFalse((new ImportService())->importWeapon($data));
    }

    //| components

    public function testRemovedComponentsAreDeleted(): void
    {
        $this->importer->importWeapon($this->weaponData([$this->componentData('Barrel'), $this->componentData('Receiver'), $this->componentData('Stock')]));
        (new ImportService())->importWeapon($this->weaponData([$this->componentData('Barrel'), $this->componentData('Receiver')]));

        $this->assertEquals(['Import Test Rifle Barrel', 'Import Test Rifle Receiver'], $this->getTestComponents()->pluck('name')->all());
    }

    public function testRemovedComponentsOwnedByUsersAreKept(): void
    {
        $this->importer->importWeapon($this->weaponData([$this->componentData('Barrel'), $this->componentData('Receiver'), $this->componentData('Stock')]));
        $stock = $this->getTestComponents()->firstWhere('name', 'Import Test Rifle Stock');
        $this->createUserComponent($this->getAdminUser()->uuid, $stock->uuid);

        (new ImportService())->importWeapon($this->weaponData([$this->componentData('Barrel'), $this->componentData('Receiver')]));

        $this->assertCount(3, $this->getTestComponents());
    }

    //| blacklist

    public function testBlacklistedWeaponIsDeleted(): void
    {
        $this->createTestWeapon(self::weaponId);

        $removed = $this->importer->importWeapon($this->weaponData([], ['type' => 'Zaw Component']));

        $this->assertTrue($removed);
        $this->assertFalse(Weapon::where('id', self::weaponId)->exists());
    }

    public function testBlacklistedWeaponOwnedByUserIsKept(): void
    {
        $this->createTestWeapon(self::weaponId);
        $this->createUserWeapon($this->getAdminUser()->uuid, self::weaponId);

        $removed = $this->importer->importWeapon($this->weaponData([], ['type' => 'Zaw Component']));

        $this->assertFalse($removed);
        $this->assertTrue(Weapon::where('id', self::weaponId)->exists());
    }

    //| pruning

    public function testPruneRemovesWeaponsThatWereNotImported(): void
    {
        $stale = $this->createTestWeapon();
        $staleComponent = $this->createTestComponent($stale->id);
        $owned = $this->createTestWeapon();
        $this->createUserWeapon($this->getAdminUser()->uuid, $owned->id);

        $this->importer->importWeapon($this->weaponData([]));
        $this->importer->pruneWeapons();

        $this->assertFalse(Weapon::where('id', $stale->id)->exists());
        $this->assertFalse(Component::where('uuid', $staleComponent->uuid)->exists());
        $this->assertTrue(Weapon::where('id', $owned->id)->exists());
        $this->assertTrue(Weapon::where('id', self::weaponId)->exists());
    }

    public function testPruneWithoutImportRemovesNothing(): void
    {
        $weapon = $this->createTestWeapon();

        $this->assertEquals(0, $this->importer->pruneWeapons());
        $this->assertTrue(Weapon::where('id', $weapon->id)->exists());
    }

    public function testPruneKeepsWarframeWithOwnedExaltedWeapon(): void
    {
        $warframe = $this->createTestWarframe();
        $exalted = $this->createTestWeapon(null, 'melee', $warframe->id);
        $this->createUserWeapon($this->getAdminUser()->uuid, $exalted->id);

        $this->importer->importWarframe([
            'uniqueName' => '/Lotus/Powersuits/ImportTest/TestFrame',
            'name' => 'Import Test Frame',
            'isPrime' => false,
        ]);
        $this->importer->pruneWarframes();

        $this->assertTrue(\App\Models\Arsenal\Warframe::where('id', $warframe->id)->exists());
        $this->assertTrue(Weapon::where('id', $exalted->id)->exists());
    }
}
