<?php

namespace App\Service\DnD\Restaurant;

use App\Models\DnD\Restaurant\Equipment;
use App\Models\DnD\Restaurant\Ingredient;
use App\Models\DnD\Restaurant\Recipe;
use App\Models\DnD\Restaurant\RecipeByproduct;
use App\Models\DnD\Restaurant\RecipeEquipment;
use App\Models\DnD\Restaurant\RecipeIngredient;
use App\Models\DnD\Restaurant\Restaurant;

class RecipeService
{
    /** @var string The component kind for the ingredients a recipe requires */
    public const string INGREDIENTS = 'ingredients';
    /** @var string The component kind for the equipment a recipe requires */
    public const string EQUIPMENT = 'equipment';
    /** @var string The component kind for the ingredients a recipe produces as byproducts */
    public const string BYPRODUCTS = 'byproducts';

    /** @var array Per component kind: the link model, the column holding the component uuid, and the component model */
    private const array COMPONENTS = [
        self::INGREDIENTS => [RecipeIngredient::class, 'ingredient_uuid', Ingredient::class],
        self::EQUIPMENT   => [RecipeEquipment::class, 'equipment_uuid', Equipment::class],
        self::BYPRODUCTS  => [RecipeByproduct::class, 'ingredient_uuid', Ingredient::class],
    ];

    /**
     * Gets every component kind a recipe has
     * @return string[] The component kinds
     */
    public function getComponentKinds(): array
    {
        return array_keys(self::COMPONENTS);
    }

    /**
     * Gets the model the components of a kind are instances of
     * @param string $kind The component kind
     * @return class-string<Ingredient|Equipment> The component model
     */
    public function getComponentModel(string $kind): string
    {
        return self::COMPONENTS[$kind][2];
    }

    /**
     * Turns a list of components into amounts keyed by uuid, checking that every component belongs to the restaurant
     * @param Restaurant $restaurant The restaurant the components must belong to
     * @param string $kind The component kind
     * @param array $entries The components, each with a uuid and an amount
     * @return array<string, int>|null The amounts keyed by uuid, or null when a component does not belong to the restaurant
     */
    public function resolveComponents(Restaurant $restaurant, string $kind, array $entries): ?array
    {
        $amounts = [];
        foreach ($entries as $entry) {
            $amounts[$entry['uuid']] = (int) $entry['amount'];
        }

        $model = $this->getComponentModel($kind);
        $found = $model::where('restaurant_uuid', $restaurant->uuid)->whereIn('uuid', array_keys($amounts))->count();
        return $found === count($amounts) ? $amounts : null;
    }

    /**
     * Replaces the components of a kind of a recipe, removing those that are no longer listed
     * @param Recipe $recipe The recipe
     * @param string $kind The component kind
     * @param array<string, int> $amounts The component amounts keyed by component uuid
     */
    public function syncComponents(Recipe $recipe, string $kind, array $amounts): void
    {
        [$link, $key] = self::COMPONENTS[$kind];

        $link::where('recipe_uuid', $recipe->uuid)->whereNotIn($key, array_keys($amounts))->delete();
        $existing = $link::where('recipe_uuid', $recipe->uuid)->get()->keyBy($key);

        foreach ($amounts as $uuid => $amount) {
            $entry = $existing->get($uuid) ?? new $link();
            $entry->recipe_uuid = $recipe->uuid;
            $entry->{$key} = $uuid;
            $entry->amount = $amount;
            $entry->save();
        }
    }
}
