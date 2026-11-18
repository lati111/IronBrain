<?php

namespace App\Service\DnD\Restaurant;

use App\Enum\DnD\Restaurant\EquipmentCategory;
use App\Enum\DnD\Restaurant\EquipmentStage;
use App\Models\DnD\Restaurant\Equipment;
use App\Models\DnD\Restaurant\Meal;
use App\Models\DnD\Restaurant\Restaurant;
use InvalidArgumentException;

/**
 * The kitchen equipment meals are made with. A meal keeps a snapshot of the equipment its recipe takes, as entries of
 * equipment_uuid, amount, stage and container. Equipment goes in use for the stage it is needed in, and comes out of
 * use either clean, when it went unused, or dirty, waiting to be cleaned. Containers hold the meal past their stage
 */
class EquipmentService
{
    public function __construct(private readonly InventoryService $inventoryService)
    {
    }

    //| Using equipment

    /**
     * Gets how many pieces of a piece of equipment are clean: those owned or stored that are neither dirty nor in use
     * @param Equipment $equipment The equipment
     * @param int|null $total How many pieces are owned or stored, when already known
     * @return int How many pieces are clean
     */
    public function getCleanAmount(Equipment $equipment, ?int $total = null): int
    {
        $total ??= $this->inventoryService->getTotalStock($equipment);
        return max(0, $total - $equipment->amount_in_use - $equipment->amount_dirty);
    }

    /**
     * Puts a meal's equipment for a stage in use, checking first that enough of all of it is clean
     * @param Restaurant $restaurant The restaurant
     * @param array $entries The meal's equipment, as equipment_uuid, amount, stage and container
     * @param EquipmentStage $stage The stage whose equipment goes in use
     * @throws InvalidArgumentException Thrown when too little of a piece of equipment is clean
     */
    public function useEquipment(Restaurant $restaurant, array $entries, EquipmentStage $stage): void
    {
        $needed = $this->sumEquipment(array_filter($entries, fn (array $entry) => $entry['stage'] === $stage->value));

        // Every piece is checked before any goes in use, so a shortage leaves everything as it was
        $pieces = [];
        foreach ($needed as $uuid => $amount) {
            $equipment = $this->findLocked($restaurant, $uuid);
            if ($equipment === null) {
                continue;
            }

            $clean = $this->getCleanAmount($equipment);
            if ($clean < $amount) {
                throw new InvalidArgumentException(sprintf('Not enough clean %s: %d needed, %d clean', $equipment->name, $amount, $clean));
            }

            $pieces[] = [$equipment, $amount];
        }

        foreach ($pieces as [$equipment, $amount]) {
            $equipment->amount_in_use += $amount;
            $equipment->save();
        }
    }

    /**
     * Takes equipment out of use, either back to clean or as dirty
     * @param Restaurant $restaurant The restaurant
     * @param array $entries The equipment, as equipment_uuid and amount
     * @param bool $dirty Whether it was used and is now dirty, rather than returned unused
     */
    public function releaseEquipment(Restaurant $restaurant, array $entries, bool $dirty): void
    {
        foreach ($entries as $entry) {
            $equipment = $this->findLocked($restaurant, $entry['equipment_uuid']);
            if ($equipment === null) {
                continue;
            }

            $equipment->amount_in_use = max(0, $equipment->amount_in_use - $entry['amount']);
            if ($dirty) {
                $equipment->amount_dirty += $entry['amount'];
            }
            $equipment->save();
        }
    }

    /**
     * Swaps the equipment a meal takes in a stage for the chosen variants of it. What was carried over from meals put
     * in stays as it is, since that is in use already
     * @param Restaurant $restaurant The restaurant
     * @param array $entries The meal's equipment, as equipment_uuid, amount, stage and container
     * @param EquipmentStage $stage The stage whose equipment is swapped
     * @param array<string, string> $substitutes The chosen equipment uuid, keyed by the uuid of the equipment it replaces
     * @param array<string, int> $carried The amounts carried over, keyed by equipment uuid
     * @return array The equipment, as equipment_uuid, amount, stage and container
     * @throws InvalidArgumentException Thrown when a chosen piece isn't a variant of the equipment it replaces
     */
    public function substituteEquipment(Restaurant $restaurant, array $entries, EquipmentStage $stage, array $substitutes, array $carried = []): array
    {
        $result = [];
        foreach ($entries as $entry) {
            if ($entry['stage'] !== $stage->value) {
                $result[] = $entry;
                continue;
            }

            // Carried over equipment is matched in the same order withoutCarriedEquipment matches it
            $base = $entry['equipment_uuid'];
            $covered = min($entry['amount'], $carried[$base] ?? 0);
            $carried[$base] = ($carried[$base] ?? 0) - $covered;

            $chosen = $substitutes[$base] ?? null;
            if ($chosen === null || $chosen === '' || $chosen === $base || $entry['amount'] <= $covered) {
                $result[] = $entry;
                continue;
            }

            $variant = Equipment::where('uuid', $chosen)->where('restaurant_uuid', $restaurant->uuid)->first();
            if ($variant === null || $variant->parent_uuid !== $base || $variant->category === EquipmentCategory::COOKER) {
                throw new InvalidArgumentException('That equipment cannot be used instead');
            }

            if ($covered > 0) {
                $result[] = array_merge($entry, ['amount' => $covered]);
            }
            $result[] = array_merge($entry, ['equipment_uuid' => $variant->uuid, 'amount' => $entry['amount'] - $covered]);
        }

        return $result;
    }

    //| A meal's equipment

    /**
     * Gets a meal's equipment for a stage: all of it, or either the containers or the rest
     * @param Meal $meal The meal
     * @param EquipmentStage $stage The stage
     * @param bool|null $container Whether to get the containers rather than the equipment that isn't one, or null for both
     * @return array The equipment, as equipment_uuid, amount, stage and container
     */
    public function filterEquipment(Meal $meal, EquipmentStage $stage, ?bool $container = null): array
    {
        return array_values(array_filter($meal->equipment ?? [],
            fn (array $entry) => $entry['stage'] === $stage->value && ($container === null || (bool) $entry['container'] === $container)));
    }

    /**
     * Gets the equipment a meal currently holds. Prep equipment is held while planned, and prep containers until the
     * meal cooks. Cooking equipment is held while cooking, and cooking containers until the meal is gone
     * @param Meal $meal The meal
     * @return array The equipment, as equipment_uuid, amount, stage and container
     */
    public function getEquipmentInUse(Meal $meal): array
    {
        return array_values(array_filter($meal->equipment ?? [], fn (array $entry) => $this->holdsEquipment($meal, $entry)));
    }

    /**
     * Checks whether a meal currently holds an entry of its equipment, as described by getEquipmentInUse
     * @param Meal $meal The meal
     * @param array $entry The equipment entry, as equipment_uuid, amount, stage and container
     * @return bool Whether the meal holds it
     */
    private function holdsEquipment(Meal $meal, array $entry): bool
    {
        $cooking = $meal->status === Meal::STATUS_COOKING;
        $cooked = $cooking || $meal->status === Meal::STATUS_SERVING;

        return match ($entry['stage']) {
            EquipmentStage::PREP->value => $meal->status === Meal::STATUS_PLANNED || ($meal->status === Meal::STATUS_PREPPED && $entry['container']),
            EquipmentStage::COOKING->value => ($cooking && !$meal->cooking_settled) || ($cooked && $entry['container']),
            default => false,
        };
    }

    //| Carried over equipment

    /**
     * Hands the equipment a finished meal holds over to the meal it is put into, as far as that meal uses the same
     * equipment. Pieces stand in for those the receiving meal already holds, which go back clean, and otherwise are
     * kept in use for its cooking. The pieces handed over are taken off the finished meal, so they aren't washed with it
     * @param Restaurant $restaurant The restaurant
     * @param Meal $source The finished meal
     * @param Meal $meal The meal it is put into
     */
    public function carryEquipment(Restaurant $restaurant, Meal $source, Meal $meal): void
    {
        $held = $this->sumEquipment($this->getEquipmentInUse($meal));
        $cooking = $this->sumEquipment($this->filterEquipment($meal, EquipmentStage::COOKING));
        $carried = $meal->carried_equipment ?? [];
        $sourceEquipment = $source->equipment ?? [];

        foreach ($this->sumEquipment($this->getEquipmentInUse($source)) as $uuid => $amount) {
            $swapped = min($amount, $held[$uuid] ?? 0);
            $kept = min($amount - $swapped, max(0, ($cooking[$uuid] ?? 0) - ($carried[$uuid] ?? 0)));

            if ($swapped > 0) {
                $this->releaseEquipment($restaurant, [['equipment_uuid' => $uuid, 'amount' => $swapped]], dirty: false);
            }
            if ($kept > 0) {
                $carried[$uuid] = ($carried[$uuid] ?? 0) + $kept;
            }

            // Whatever was handed over no longer belongs to the finished meal
            $handed = $swapped + $kept;
            foreach ($sourceEquipment as $index => $entry) {
                if ($handed <= 0) {
                    break;
                }
                if ($entry['equipment_uuid'] === $uuid && $this->holdsEquipment($source, $entry)) {
                    $taken = min($entry['amount'], $handed);
                    $sourceEquipment[$index]['amount'] -= $taken;
                    $handed -= $taken;
                }
            }
        }

        $meal->carried_equipment = $carried !== [] ? $carried : null;
        $source->equipment = $sourceEquipment;
    }

    /**
     * Gets the equipment carried over to a meal from meals put into it, which is kept in use for its cooking
     * @param Meal $meal The meal
     * @return array The equipment, as equipment_uuid and amount
     */
    public function getCarriedEquipment(Meal $meal): array
    {
        return array_map(fn (string $uuid, int $amount) => ['equipment_uuid' => $uuid, 'amount' => $amount],
            array_keys($meal->carried_equipment ?? []), array_values($meal->carried_equipment ?? []));
    }

    /**
     * Gets a meal's equipment without the cooking equipment carried over to it, which is in use already
     * @param Meal $meal The meal
     * @return array The equipment, as equipment_uuid, amount, stage and container
     */
    public function withoutCarriedEquipment(Meal $meal): array
    {
        $carried = $meal->carried_equipment ?? [];
        $entries = [];
        foreach ($meal->equipment ?? [] as $entry) {
            if ($entry['stage'] === EquipmentStage::COOKING->value && ($carried[$entry['equipment_uuid']] ?? 0) > 0) {
                $covered = min($entry['amount'], $carried[$entry['equipment_uuid']]);
                $carried[$entry['equipment_uuid']] -= $covered;
                $entry['amount'] -= $covered;
            }
            $entries[] = $entry;
        }

        return $entries;
    }

    //| Helpers

    /**
     * Adds up equipment entries per piece of equipment
     * @param array $entries The equipment, as equipment_uuid and amount
     * @return array<string, int> The amounts keyed by equipment uuid
     */
    private function sumEquipment(array $entries): array
    {
        $amounts = [];
        foreach ($entries as $entry) {
            $amounts[$entry['equipment_uuid']] = ($amounts[$entry['equipment_uuid']] ?? 0) + $entry['amount'];
        }

        return $amounts;
    }

    /**
     * Gets a piece of the restaurant's equipment, locked so its amounts can be changed safely
     * @param Restaurant $restaurant The restaurant
     * @param string $uuid The uuid of the equipment
     * @return Equipment|null The equipment, or null if it no longer exists
     */
    private function findLocked(Restaurant $restaurant, string $uuid): ?Equipment
    {
        return Equipment::where('uuid', $uuid)->where('restaurant_uuid', $restaurant->uuid)->lockForUpdate()->first();
    }
}
