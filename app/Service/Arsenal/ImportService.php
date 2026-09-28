<?php

namespace App\Service\Arsenal;

use App\Models\Arsenal\Companion;
use App\Models\Arsenal\Component;
use App\Models\Arsenal\Interfaces\ArsenalDataModel;
use App\Models\Arsenal\Relic;
use App\Models\Arsenal\UserCompanion;
use App\Models\Arsenal\UserComponent;
use App\Models\Arsenal\UserWarframe;
use App\Models\Arsenal\UserWeapon;
use App\Models\Arsenal\Warframe;
use App\Models\Arsenal\Weapon;
use App\Service\Traits\DownloadsImages;
use App\Service\Traits\PreloadsModels;
use Closure;

class ImportService
{
    use DownloadsImages, PreloadsModels;

    private const string imageUrl = 'https://raw.githubusercontent.com/WFCD/warframe-items/refs/heads/master/data/img';

    /** @var array<class-string<ArsenalDataModel>, array<string, true>> The ids of the items imported during this run, per model */
    private array $importedItems = [];

    /** @var array<string, array<string, true>> The uuids of the components imported during this run, per blueprint id */
    private array $importedComponents = [];

    /**
     * Sets the database connection used by the importer
     * @param string $connection The name of the connection
     */
    public function setConnection(string $connection): void {
        \Config::set('database.connections.mysql.database', $connection);
        \DB::purge('mysql');
        $this->clearPreloaded();
    }

    /**
     * Parses the data from an array and saves them to the database as a warframe
     * @param array $data An array containing the data
     * @return bool Returns whether the warframe was changed/saved or not
     */
    public function importWarframe(array $data): bool
    {
        $warframe = $this->preloaded(Warframe::class, 'id')->get($data['uniqueName']);

        // Handle blacklist
        if (
            ($data['productCategory'] ?? 'none') === 'MechSuits' ||
            $data['uniqueName'] === '/Lotus/Powersuits/PowersuitAbilities/Helminth'
        ) {
            return $warframe !== null && $this->removeItem($warframe);
        }

        // Create new
        if ($warframe === null) {
            $warframe = new Warframe();
            $warframe->id = $data['uniqueName'];
            $this->preloaded(Warframe::class, 'id')->put($warframe->id, $warframe);
        }
        $this->importedItems[Warframe::class][$warframe->id] = true;

        // Update contents
        $warframe->name = $data['name'];
        $warframe->description = $data['description'] ?? '';
        $warframe->wiki_url = $data['wikiaUrl'] ?? null;
        $warframe->prime = $data['isPrime'];
        $warframe->vaulted = $data['vaulted'] ?? false;
        $warframe->icon = isset($data['imageName']) ? $this->importAsset($data['imageName'], 'warframe') : null;
        $warframe->save();
        $updated = $warframe->wasRecentlyCreated || $warframe->wasChanged();

        // Update components
        $updated = $this->importComponents($data['components'] ?? [], $warframe, 'warframe', false) || $updated;

        // Link exalted weapons to this warframe
        foreach ($data['exaltedWeapons'] ?? [] as $exaltedWeaponId) {
            $exaltedWeapon = $this->preloaded(Weapon::class, 'id')->get($exaltedWeaponId);
            if ($exaltedWeapon !== null && $exaltedWeapon->exalted_id !== $warframe->id) {
                $exaltedWeapon->exalted_id = $warframe->id;
                $exaltedWeapon->save();
                $updated = true;
            }
        }

        return $updated;
    }

    /**
     * Parses the data from an array and saves them to the database as a weapon
     * @param array $data An array containing the data
     * @return bool Returns whether the weapon was changed/saved or not
     */
    public function importWeapon(array $data): bool
    {
        $weapon = $this->preloaded(Weapon::class, 'id')->get($data['uniqueName']);

        // Handle blacklist
        if (
            ($data['type'] ?? 'none') === 'Zaw Component' && !str_contains($data['uniqueName'], '/Tip/') ||
            ($data['type'] ?? 'none') === 'Zaw Component' && str_contains($data['uniqueName'], '/PvPVariant')
        ) {
            return $weapon !== null && $this->removeItem($weapon);
        }

        // Create new
        if ($weapon === null) {
            $weapon = new Weapon();
            $weapon->id = $data['uniqueName'];
            $this->preloaded(Weapon::class, 'id')->put($weapon->id, $weapon);
        }
        $this->importedItems[Weapon::class][$weapon->id] = true;

        // Mutate data
        if ($data['type'] === 'Companion Weapon') {
            $data['category'] = 'Companion Weapon';
        }

        // Update contents
        $weapon->name = $data['name'];
        $weapon->description = $data['description'] ?? '';
        $weapon->type = match (strtolower($data['category'])) {
            'arch-gun'         => 'archgun',
            'arch-melee'       => 'archmelee',
            'companion weapon' => 'companion_weapon',
            default            => strtolower($data['category']),
        };
        $weapon->weapon_type = $data['type'];
        $weapon->wiki_url = $data['wikiaUrl'] ?? null;
        $weapon->prime = $data['isPrime'] ?? false;
        $weapon->vaulted = $data['vaulted'] ?? false;
        $weapon->icon = isset($data['imageName']) ? $this->importAsset($data['imageName'], 'weapon') : null;
        $weapon->save();
        $updated = $weapon->wasRecentlyCreated || $weapon->wasChanged();

        // Update components
        return $this->importComponents($data['components'] ?? [], $weapon, 'weapon', true) || $updated;
    }

    /**
     * Parses the data from an array and saves them to the database as a companion
     * @param array $data An array containing the data
     * @return bool Returns whether the companion was changed/saved or not
     */
    public function importCompanion(array $data): bool
    {
        $companion = $this->preloaded(Companion::class, 'id')->get($data['uniqueName']);

        // Handle blacklist
        if (($data['productCategory'] ?? 'none') === 'Pistols') {
            return $companion !== null && $this->removeItem($companion);
        }

        // Create new
        if ($companion === null) {
            $companion = new Companion();
            $companion->id = $data['uniqueName'];
            $this->preloaded(Companion::class, 'id')->put($companion->id, $companion);
        }
        $this->importedItems[Companion::class][$companion->id] = true;

        // Update contents
        $companion->name = $data['name'];
        $companion->description = $data['description'] ?? '';
        $companion->type = $data['category'];
        $companion->pet_type = $data['productCategory'];
        $companion->wiki_url = $data['wikiaUrl'] ?? null;
        $companion->prime = $data['isPrime'];
        $companion->icon = isset($data['imageName']) ? $this->importAsset($data['imageName'], 'companion') : null;
        $companion->save();
        $updated = $companion->wasRecentlyCreated || $companion->wasChanged();

        // Update components
        return $this->importComponents($data['components'] ?? [], $companion, 'companion', true) || $updated;
    }

    /**
     * Imports the components of a blueprint, and removes the components the blueprint no longer has
     * @param array $components The component data
     * @param ArsenalDataModel $blueprint The blueprint
     * @param string $type The type of blueprint
     * @param bool $dropSingleComponent Whether to remove the component if the blueprint only has one, as it is then the item itself
     * @return bool Whether any of the components were updated
     */
    private function importComponents(array $components, ArsenalDataModel $blueprint, string $type, bool $dropSingleComponent): bool
    {
        $updated = false;
        $occurrences = [];
        foreach ($components as $component) {
            $occurrences[$component['uniqueName']] = ($occurrences[$component['uniqueName']] ?? 0) + 1;
            $updated = $this->importComponent($component, $blueprint, $type, $occurrences[$component['uniqueName']]) || $updated;
        }

        // Remove the components this blueprint no longer has
        $imported = $this->importedComponents[$blueprint->id] ?? [];
        $remaining = $this->getComponents($blueprint);
        foreach ($remaining as $key => $component) {
            if (!isset($imported[$component->uuid]) && $this->removeItem($component)) {
                $remaining->forget($key);
                $updated = true;
            }
        }

        if ($dropSingleComponent && $remaining->count() === 1) {
            $this->removeItem($remaining->first());
            return false;
        }

        return $updated;
    }

    /**
     * Import a component from a blueprint
     * @param array $data The component data
     * @param ArsenalDataModel $blueprint The blueprint
     * @param string $type The type of blueprint
     * @param int $occurrence How many times this component has occurred in the blueprint so far
     * @return bool Whether or not the component was updated
     */
    public function importComponent(array $data, ArsenalDataModel $blueprint, string $type, int $occurrence = 1): bool
    {
        $effectiveId = $data['uniqueName'] . ($occurrence > 1 ? '-' . $occurrence : '');
        $key = $this->componentKey($blueprint->id, $effectiveId);
        $component = $this->preloaded(Component::class, fn (Component $c) => $this->componentKey($c->blueprint_id, $c->id))->get($key);

        // Handle blacklist
        if ($this->isBlacklistedComponent($data)) {
            return $component !== null && $this->removeItem($component);
        }

        // Create new
        if ($component === null) {
            $component = new Component();
            $component->id = $effectiveId;
            $component->blueprint_id = $blueprint->id;
            $component->type = $type;
        }

        // Update content
        $component->name = ucfirst(sprintf('%s %s', $blueprint->name, $data['name']));
        $component->amount = $data['itemCount'];
        $component->icon = isset($data['imageName']) ? $this->importAsset($data['imageName'], 'component') : null;
        $component->save();
        $updated = $component->wasRecentlyCreated || $component->wasChanged();

        $this->preloaded(Component::class, fn (Component $c) => $this->componentKey($c->blueprint_id, $c->id))->put($key, $component);
        $this->importedComponents[$blueprint->id][$component->uuid] = true;

        // Import relics
        foreach ($data['drops'] ?? [] as $relic) {
            $updated = $this->importRelic($relic, $component) || $updated;
        }

        return $updated;
    }

    /**
     * Import a relic a component drops from
     * @param array $data The drop data
     * @param Component $component The component this relic is sourced from
     * @return bool Whether or not the relic was updated
     */
    public function importRelic(array $data, Component $component): bool
    {
        // Handle blacklist
        if (!str_ends_with($data['location'], 'Relic') || str_starts_with($data['location'], 'Vanguard')) {
            return false;
        }

        $nameParts = explode(' ', $data['location']);
        $relicName = $nameParts[0] . ' ' . $nameParts[1];
        $key = $component->uuid . '|' . $relicName;
        $relic = $this->preloaded(Relic::class, fn (Relic $r) => $r->component_uuid . '|' . $r->name)->get($key);

        // Create new
        if ($relic === null) {
            $relic = new Relic();
            $relic->component_uuid = $component->uuid;
            $relic->name = $relicName;
            $this->preloaded(Relic::class, fn (Relic $r) => $r->component_uuid . '|' . $r->name)->put($key, $relic);
        }

        $relic->grade = $nameParts[0];
        $relic->key = $nameParts[1];
        $relic->rarity = $data['rarity'];
        $relic->save();

        return $relic->wasRecentlyCreated || $relic->wasChanged();
    }

    /**
     * Removes the warframes that were not part of this import, unless they are in use
     * @return int Returns the amount of warframes removed
     */
    public function pruneWarframes(): int
    {
        return $this->pruneItems(Warframe::class);
    }

    /**
     * Removes the weapons that were not part of this import, unless they are in use
     * @return int Returns the amount of weapons removed
     */
    public function pruneWeapons(): int
    {
        return $this->pruneItems(Weapon::class);
    }

    /**
     * Removes the companions that were not part of this import, unless they are in use
     * @return int Returns the amount of companions removed
     */
    public function pruneCompanions(): int
    {
        return $this->pruneItems(Companion::class);
    }

    /**
     * Downloads the icons of a list of items and their components in parallel, to speed up importing them
     * @param array $items The items as returned by the API
     * @param string $category The asset category of the items
     * @param ?Closure(int $done, int $total): void $onProgress Called as the images are downloaded
     */
    public function prefetchAssets(array $items, string $category, ?Closure $onProgress = null): void
    {
        $urls = [];
        foreach ($items as $item) {
            if (isset($item['imageName'])) {
                $urls[$this->assetPath($item['imageName'], $category)] = $this->assetUrl($item['imageName']);
            }

            foreach ($item['components'] ?? [] as $component) {
                if (isset($component['imageName']) && !$this->isBlacklistedComponent($component)) {
                    $urls[$this->assetPath($component['imageName'], 'component')] = $this->assetUrl($component['imageName']);
                }
            }
        }

        $this->prefetchImages($urls, $onProgress);
    }

    /**
     * Import an asset if it does not exist yet
     * @param string $assetName The name of the asset
     * @param string $category The asset category
     * @return ?string Returns the storage path of the asset, or null if it could not be imported
     */
    protected function importAsset(string $assetName, string $category): ?string {
        $imgPath = $this->assetPath($assetName, $category);
        return $this->downloadImage($this->assetUrl($assetName), $imgPath) ? $imgPath : null;
    }

    /**
     * Removes the items of a model that were not part of this import, unless they are in use
     * @param class-string<ArsenalDataModel> $model The model to prune
     * @return int Returns the amount of items removed
     */
    private function pruneItems(string $model): int
    {
        // Never prune when nothing was imported, as that would remove every item
        $imported = $this->importedItems[$model] ?? [];
        if ($imported === []) {
            return 0;
        }

        $removed = 0;
        foreach ($this->preloaded($model, 'id')->values() as $item) {
            if (!isset($imported[$item->id]) && $this->removeItem($item)) {
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * Deletes an item along with its components, unless a user owns the item or one of its components.
     * Deleting items cascades to user data, so items that are in use are always kept.
     * @param ArsenalDataModel $item The item to remove
     * @return bool Returns whether the item was removed
     */
    private function removeItem(ArsenalDataModel $item): bool
    {
        if ($this->isInUse($item)) {
            return false;
        }

        if ($item instanceof Component) {
            $this->preloaded(Component::class, fn (Component $c) => $this->componentKey($c->blueprint_id, $c->id))
                ->forget($this->componentKey($item->blueprint_id, $item->id));
            $item->delete();
            return true;
        }

        // Exalted weapons are deleted along with their warframe
        if ($item instanceof Warframe) {
            foreach (Weapon::where('exalted_id', $item->id)->get() as $exaltedWeapon) {
                $this->removeItem($exaltedWeapon);
            }
        }

        foreach ($this->getComponents($item) as $component) {
            $this->removeItem($component);
        }

        $this->preloaded($item::class, 'id')->forget($item->id);
        $item->delete();
        return true;
    }

    /**
     * Checks whether a user owns an item, or one of its components
     * @param ArsenalDataModel $item The item to check
     * @return bool Returns whether the item is in use
     */
    private function isInUse(ArsenalDataModel $item): bool
    {
        if ($item instanceof Component) {
            return UserComponent::where('id', $item->uuid)->exists();
        }

        $userModel = match ($item::class) {
            Warframe::class => UserWarframe::class,
            Weapon::class => UserWeapon::class,
            Companion::class => UserCompanion::class,
        };

        if ($userModel::where('id', $item->id)->exists()) {
            return true;
        }

        if (UserComponent::whereIn('id', Component::select('uuid')->where('blueprint_id', $item->id))->exists()) {
            return true;
        }

        if ($item instanceof Warframe) {
            return Weapon::where('exalted_id', $item->id)->get()->contains(fn (Weapon $weapon) => $this->isInUse($weapon));
        }

        return false;
    }

    /**
     * Gets the components of a blueprint
     * @param ArsenalDataModel $blueprint The blueprint
     * @return \Illuminate\Support\Collection<Component> Returns the components
     */
    private function getComponents(ArsenalDataModel $blueprint): \Illuminate\Support\Collection
    {
        return $this->preloaded(Component::class, fn (Component $c) => $this->componentKey($c->blueprint_id, $c->id))
            ->filter(fn (Component $component) => $component->blueprint_id === $blueprint->id)
            ->toBase();
    }

    /**
     * Checks whether a component should not be imported, eg. because it is a resource
     * @param array $data The component data
     * @return bool Returns whether the component is blacklisted
     */
    private function isBlacklistedComponent(array $data): bool
    {
        return ($data['type'] ?? 'none') === 'Resource' ||
            str_starts_with($data['uniqueName'], '/Lotus/Types/Items/Gems/') ||
            str_starts_with($data['uniqueName'], '/Lotus/Types/Gameplay/') ||
            str_starts_with($data['uniqueName'], '/Lotus/Types/Items/MiscItems/') ||
            str_starts_with($data['uniqueName'], '/Lotus/Types/Items/RailjackMiscItems/') ||
            str_starts_with($data['uniqueName'], '/Lotus/Types/Items/Research/') ||
            str_starts_with($data['uniqueName'], '/Lotus/Types/Items/Fish');
    }

    /**
     * Gets the key used to look up a preloaded component
     * @param string $blueprintId The id of the blueprint
     * @param string $componentId The id of the component
     * @return string Returns the key
     */
    private function componentKey(string $blueprintId, string $componentId): string
    {
        return $blueprintId . '|' . $componentId;
    }

    /**
     * Gets the storage path of an asset
     * @param string $assetName The name of the asset
     * @param string $category The asset category
     * @return string Returns the storage path
     */
    private function assetPath(string $assetName, string $category): string
    {
        return sprintf('modules/arsenal/%s/%s', $category, $assetName);
    }

    /**
     * Gets the url of an asset
     * @param string $assetName The name of the asset
     * @return string Returns the url
     */
    private function assetUrl(string $assetName): string
    {
        return sprintf('%s/%s', self::imageUrl, $assetName);
    }
}
