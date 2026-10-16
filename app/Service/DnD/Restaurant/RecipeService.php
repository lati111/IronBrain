<?php

namespace App\Service\DnD\Restaurant;

use App\Enum\DnD\Restaurant\EquipmentStage;
use App\Models\DnD\Restaurant\Equipment;
use App\Models\DnD\Restaurant\Ingredient;
use App\Models\DnD\Restaurant\Recipe;
use App\Models\DnD\Restaurant\RecipeByproduct;
use App\Models\DnD\Restaurant\RecipeCategory;
use App\Models\DnD\Restaurant\RecipeEquipment;
use App\Models\DnD\Restaurant\RecipeIngredient;
use App\Models\DnD\Restaurant\RecipeMeal;
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
     * Turns a list of components into the attributes to save for each, keyed by uuid, checking that every component
     * belongs to the restaurant. Equipment also gets when it is used and whether it is a container
     * @param Restaurant $restaurant The restaurant the components must belong to
     * @param string $kind The component kind
     * @param array $entries The components, each with a uuid and an amount, and for equipment a stage and container
     * @return array<string, array>|null The attributes keyed by uuid, or null when a component does not belong to the restaurant
     */
    public function resolveComponents(Restaurant $restaurant, string $kind, array $entries): ?array
    {
        $model = $this->getComponentModel($kind);
        $components = [];
        foreach ($entries as $entry) {
            if ($model === Equipment::class) {
                // Equipment comes in whole pieces. Equipment used for serving can't be a container
                $stage = EquipmentStage::tryFrom($entry['stage'] ?? '') ?? EquipmentStage::PREP;
                $components[$entry['uuid']] = [
                    'amount' => (int) $entry['amount'],
                    'stage' => $stage,
                    'container' => $stage->allowsContainer() && filter_var($entry['container'] ?? false, FILTER_VALIDATE_BOOLEAN),
                ];
            } else {
                // Ingredient amounts can be fractional and are stored with two decimals
                $components[$entry['uuid']] = ['amount' => round((float) $entry['amount'], 2)];
            }
        }

        $found = $model::where('restaurant_uuid', $restaurant->uuid)->whereIn('uuid', array_keys($components))->count();
        return $found === count($components) ? $components : null;
    }

    /**
     * Replaces the components of a kind of a recipe, removing those that are no longer listed
     * @param Recipe $recipe The recipe
     * @param string $kind The component kind
     * @param array<string, array> $components The attributes to save for each component, keyed by component uuid
     */
    public function syncComponents(Recipe $recipe, string $kind, array $components): void
    {
        [$link, $key] = self::COMPONENTS[$kind];

        $link::where('recipe_uuid', $recipe->uuid)->whereNotIn($key, array_keys($components))->delete();
        $existing = $link::where('recipe_uuid', $recipe->uuid)->get()->keyBy($key);

        foreach ($components as $uuid => $attributes) {
            $entry = $existing->get($uuid) ?? new $link();
            $entry->recipe_uuid = $recipe->uuid;
            $entry->{$key} = $uuid;
            foreach ($attributes as $attribute => $value) {
                $entry->{$attribute} = $value;
            }
            $entry->save();
        }
    }

    /**
     * Replaces the ingredients a recipe takes by category, removing those that are no longer listed
     * @param Recipe $recipe The recipe
     * @param array<string, float> $categories The amounts keyed by category
     */
    public function syncCategories(Recipe $recipe, array $categories): void
    {
        RecipeCategory::where('recipe_uuid', $recipe->uuid)->whereNotIn('category', array_keys($categories))->delete();

        foreach ($categories as $category => $amount) {
            $link = RecipeCategory::where('recipe_uuid', $recipe->uuid)->where('category', $category)->first() ?? new RecipeCategory();
            $link->recipe_uuid = $recipe->uuid;
            $link->category = $category;
            $link->amount = $amount;
            $link->save();
        }
    }

    /**
     * Replaces the other meals a recipe takes, removing those that are no longer listed. Those still listed keep
     * their entry, so meals already being made keep track of what has been put in
     * @param Recipe $recipe The recipe
     * @param array $meals The meals, each as recipe_uuid or category with portions
     */
    public function syncMeals(Recipe $recipe, array $meals): void
    {
        $existing = RecipeMeal::where('recipe_uuid', $recipe->uuid)->get()
            ->keyBy(fn (RecipeMeal $entry) => $entry->required_recipe_uuid !== null ? 'recipe:' . $entry->required_recipe_uuid : 'category:' . $entry->category);

        $kept = [];
        foreach ($meals as $meal) {
            $key = $meal['recipe_uuid'] !== null ? 'recipe:' . $meal['recipe_uuid'] : 'category:' . $meal['category'];
            $entry = $existing->get($key) ?? new RecipeMeal();
            $entry->recipe_uuid = $recipe->uuid;
            $entry->required_recipe_uuid = $meal['recipe_uuid'];
            $entry->category = $meal['category'];
            $entry->portions = $meal['portions'];
            $entry->save();
            $kept[] = $entry->uuid;
        }

        RecipeMeal::where('recipe_uuid', $recipe->uuid)->whereNotIn('uuid', $kept)->delete();
    }

    //| Variants

    /**
     * Gets how a meal a recipe takes is told apart: by the recipe it must be of, or by its category with the category
     * prefix in front
     * @param string|null $recipeUuid The uuid of the recipe the meal must be of, if any
     * @param string|null $category The category of recipe the meal must be of, when no recipe is
     * @return string The key
     */
    public function getMealKey(?string $recipeUuid, ?string $category): string
    {
        return $recipeUuid ?? 'category:' . $category;
    }

    /**
     * Rebuilds every variant of a recipe, after the recipe changed
     * @param Recipe $base The base recipe
     */
    public function rebuildVariants(Recipe $base): void
    {
        foreach (Recipe::where('parent_uuid', $base->uuid)->get() as $variant) {
            $this->rebuildVariant($variant);
        }
    }

    /**
     * Rebuilds a variant's stats and components from its base recipe with its changes applied. Its stats are its base
     * recipe's plus what it adds, never below what a recipe can have. Its lists are its base recipe's without what it
     * takes away, plus what it adds, where adding something the base recipe has already adds to its amount. What was
     * added but no longer exists is left out. The variant is saved
     * @param Recipe $variant The variant
     */
    public function rebuildVariant(Recipe $variant): void
    {
        $base = Recipe::find($variant->parent_uuid);
        if ($base === null) {
            return;
        }

        $changes = $variant->variant_changes ?? [];
        $removed = fn (string $list) => array_flip($changes['remove'][$list] ?? []);
        $added = fn (string $list) => $changes['add'][$list] ?? [];

        foreach (Recipe::VARIANT_STATS as $field) {
            $value = $base->{$field} + ($changes['stats'][$field] ?? 0);
            $variant->{$field} = match ($field) {
                'price' => max(0, round($value, 2)),
                'portions' => max(1, (int) $value),
                default => max(0, (int) $value),
            };
        }

        // The type and category are the base recipe's, so a variant is found wherever its base recipe is
        $variant->type = $base->type;
        $variant->category = $base->category;
        $variant->save();

        foreach (self::COMPONENTS as $kind => [$link, $key, $model]) {
            $isEquipment = $model === Equipment::class;
            $components = [];
            foreach ($link::where('recipe_uuid', $base->uuid)->get() as $entry) {
                $components[$entry->{$key}] = $isEquipment
                    ? ['amount' => $entry->amount, 'stage' => $entry->stage, 'container' => $entry->container]
                    : ['amount' => $entry->amount];
            }
            $components = array_diff_key($components, $removed($kind));

            $additions = $added($kind);
            $existing = $model::whereIn('uuid', array_keys($additions))->pluck('uuid')->all();
            foreach ($additions as $uuid => $attributes) {
                if (!in_array($uuid, $existing, true)) {
                    continue;
                }

                if (isset($components[$uuid])) {
                    $amount = $components[$uuid]['amount'] + $attributes['amount'];
                    $components[$uuid]['amount'] = $isEquipment ? (int) $amount : round($amount, 2);
                } else {
                    $components[$uuid] = $isEquipment
                        ? ['amount' => (int) $attributes['amount'], 'stage' => EquipmentStage::from($attributes['stage']), 'container' => (bool) $attributes['container']]
                        : ['amount' => round($attributes['amount'], 2)];
                }
            }

            $this->syncComponents($variant, $kind, $components);
        }

        $categories = array_diff_key(RecipeCategory::where('recipe_uuid', $base->uuid)->pluck('amount', 'category')->all(), $removed('categories'));
        foreach ($added('categories') as $category => $amount) {
            $categories[$category] = round(($categories[$category] ?? 0) + $amount, 2);
        }
        $this->syncCategories($variant, $categories);

        $meals = [];
        foreach (RecipeMeal::where('recipe_uuid', $base->uuid)->get() as $entry) {
            $meals[$this->getMealKey($entry->required_recipe_uuid, $entry->category)] = [
                'recipe_uuid' => $entry->required_recipe_uuid,
                'category' => $entry->category,
                'portions' => $entry->portions,
            ];
        }
        $meals = array_diff_key($meals, $removed('meals'));

        $additions = $added('meals');
        $existing = Recipe::whereIn('uuid', array_filter(array_column($additions, 'recipe_uuid')))->pluck('uuid')->all();
        foreach ($additions as $meal) {
            if ($meal['recipe_uuid'] !== null && !in_array($meal['recipe_uuid'], $existing, true)) {
                continue;
            }

            $key = $this->getMealKey($meal['recipe_uuid'], $meal['category']);
            if (isset($meals[$key])) {
                $meals[$key]['portions'] += $meal['portions'];
            } else {
                $meals[$key] = $meal;
            }
        }

        // A variant can't take a meal of itself, even when its base recipe does
        unset($meals[$variant->uuid]);
        $this->syncMeals($variant, array_values($meals));
    }
}
