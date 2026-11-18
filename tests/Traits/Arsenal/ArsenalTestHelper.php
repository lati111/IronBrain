<?php

namespace Tests\Traits\Arsenal;

use App\Models\Arsenal\Companion;
use App\Models\Arsenal\Component;
use App\Models\Arsenal\Loadout;
use App\Models\Arsenal\UserCompanion;
use App\Models\Arsenal\UserComponent;
use App\Models\Arsenal\UserWarframe;
use App\Models\Arsenal\UserWeapon;
use App\Models\Arsenal\Warframe;
use App\Models\Arsenal\Weapon;
use Illuminate\Support\Str;

trait ArsenalTestHelper
{
    protected function createTestWarframe(?string $id = null): Warframe
    {
        $warframe = new Warframe();
        $warframe->id = $id ?? 'wf_' . Str::random(8);
        $warframe->name = 'Test Warframe';
        $warframe->description = 'A test warframe';
        $warframe->prime = false;
        $warframe->save();
        return $warframe;
    }

    protected function createTestWeapon(?string $id = null, string $type = 'primary', ?string $exaltedId = null): Weapon
    {
        $weapon = new Weapon();
        $weapon->id = $id ?? 'wp_' . Str::random(8);
        $weapon->name = 'Test Weapon';
        $weapon->description = 'A test weapon';
        $weapon->type = $type;
        $weapon->weapon_type = 'rifle';
        $weapon->prime = false;
        $weapon->exalted_id = $exaltedId;
        $weapon->save();
        return $weapon;
    }

    protected function createTestCompanion(?string $id = null): Companion
    {
        $companion = new Companion();
        $companion->id = $id ?? 'cp_' . Str::random(8);
        $companion->name = 'Test Companion';
        $companion->description = 'A test companion';
        $companion->type = 'sentinel';
        $companion->pet_type = '';
        $companion->prime = false;
        $companion->save();
        return $companion;
    }

    protected function createTestComponent(string $blueprintId, int $amount = 1): Component
    {
        $component = new Component();
        $component->id = 'part_' . Str::random(8);
        $component->blueprint_id = $blueprintId;
        $component->name = 'Test Part';
        $component->type = 'warframe';
        $component->amount = $amount;
        $component->save();
        return $component;
    }

    protected function createUserWarframe(string $ownerUuid, string $warframeId): UserWarframe
    {
        $item = new UserWarframe();
        $item->id = $warframeId;
        $item->owner_uuid = $ownerUuid;
        $item->save();
        return $item;
    }

    protected function createUserWeapon(string $ownerUuid, string $weaponId): UserWeapon
    {
        $item = new UserWeapon();
        $item->id = $weaponId;
        $item->owner_uuid = $ownerUuid;
        $item->save();
        return $item;
    }

    protected function createUserCompanion(string $ownerUuid, string $companionId): UserCompanion
    {
        $item = new UserCompanion();
        $item->id = $companionId;
        $item->owner_uuid = $ownerUuid;
        $item->save();
        return $item;
    }

    protected function createUserComponent(string $ownerUuid, string $componentUuid, int $amount = 1): UserComponent
    {
        $item = new UserComponent();
        $item->id = $componentUuid;
        $item->owner_uuid = $ownerUuid;
        $item->amount = $amount;
        $item->save();
        return $item;
    }

    protected function createTestLoadout(string $ownerUuid, string $warframeUuid): Loadout
    {
        $loadout = new Loadout();
        $loadout->owner_uuid = $ownerUuid;
        $loadout->name = 'Test Loadout';
        $loadout->warframe_uuid = $warframeUuid;
        $loadout->save();
        return $loadout;
    }
}
