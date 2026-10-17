/** The shapes of the inventory as the server sends it, and the constants the management screen is built around */

export type ItemType = 'ingredient' | 'equipment';

export interface Item {
    type: ItemType;
    uuid: string;
    amount: number;
    /** The stack an ingredient is stored in. Missing in general storage, which isn't saved */
    stack_uuid?: string;
    /** The days an ingredient has left before it expires, or null when it doesn't expire or is fresh */
    days_left?: number | null;
}

/** A stack of an ingredient that expired as the date moved on */
export interface ExpiredStack {
    name: string;
    amount: number;
    location: string;
}

export interface StorageSpace {
    uuid: string;
    name: string;
    capacity: number;
    used_capacity: number;
    expiration_multiplier: number;
    background_image: string | null;
    items: Item[];
}

export interface IngredientType {
    uuid: string;
    name: string;
    /** Recipes can take any ingredient of a category */
    category: string | null;
    icon: string | null;
    size: number;
    expires_in: number;
    /** Added to the quality of meals made with it, averaged over their ingredients */
    quality: number;
    /** The ingredient this is a variant of, which it can be used instead of */
    parent_uuid: string | null;
    stored: number;
}

export interface EquipmentType {
    uuid: string;
    name: string;
    description: string | null;
    /** The internal code of the category, used when saving */
    category: string;
    /** The display name of the category */
    category_label: string;
    size: number;
    cleaning_time: number;
    multiplier: number;
    parent_uuid: string | null;
    requires_storage: boolean;
    stored: number;
}

export interface RecipeComponent {
    uuid: string;
    amount: number;
    /** For equipment: when during making the recipe it is used */
    stage?: string;
    /** For equipment: whether it holds the meal, staying in use for longer */
    container?: boolean;
}

/** When a recipe's equipment is used, and whether it holds the meal */
export interface EquipmentUsage {
    stage: string;
    container: boolean;
}

/** The stage new equipment in a recipe starts at, matching the server's default */
export const DEFAULT_EQUIPMENT_STAGE = 'prep';

/** The internal code of the cooker equipment category, which can't be part of a recipe */
export const COOKER_CATEGORY = 'cooker';

/** The recipe fields that are plain numbers, in the same order as the form */
export const RECIPE_NUMBER_FIELDS = [
    'price', 'portions', 'difficulty', 'prep_time', 'cooking_time', 'fresh_for', 'good_for', 'expires_in',
] as const;

/** The lists of a recipe, which a variant adds to in the same shape */
export interface RecipeLists {
    ingredients: RecipeComponent[];
    equipment: RecipeComponent[];
    byproducts: RecipeComponent[];
    /** Ingredients taken by category, any ingredient of which will do */
    categories: RecipeCategory[];
    /** Other meals taken, of a specific recipe or of any recipe of a category */
    meals: RecipeMealRequirement[];
}

export interface RecipeType extends RecipeLists {
    uuid: string;
    /** The base recipe this is a variant of. A variant's stats and lists are its base recipe's with its changes */
    parent_uuid: string | null;
    variant_changes: VariantChanges | null;
    name: string;
    /** What kind of recipe it is, such as a meal */
    type: string;
    /** Other recipes can require any meal of a category */
    category: string | null;
    description: string | null;
    price: number;
    portions: number;
    difficulty: number;
    prep_time: number;
    cooking_time: number;
    fresh_for: number;
    /** In hours */
    good_for: number;
    /** In days */
    expires_in: number;
}

/** The lists a variant can add to or take away from, as the server knows them */
export type VariantList = keyof RecipeLists;

/** What a variant changes about its base recipe. Its lists are what is added to the base recipe's, where adding what
 * it has already adds to the amount */
export interface VariantChanges extends RecipeLists {
    /** What is added to each of the base recipe's stats */
    stats: Record<typeof RECIPE_NUMBER_FIELDS[number], number>;
    /** What is taken away from the base recipe's lists: by uuid, by category for ingredients taken by category, and
     * by recipe uuid or CATEGORY_PREFIX and category for meals */
    removed: Record<VariantList, string[]>;
}

export interface RecipeMealRequirement {
    /** The recipe the meal must be of, or null when any recipe of the category will do */
    recipe_uuid: string | null;
    category: string | null;
    portions: number;
}

export interface RecipeCategory {
    category: string;
    amount: number;
}

/** Marks an entry of a recipe's ingredient list as any ingredient of a category, rather than a specific one */
export const CATEGORY_PREFIX = 'category:';

/** Turns an ingredient into another: each piece of the input becomes an amount of the output */
export interface ConversionType {
    uuid: string;
    input_uuid: string;
    output_uuid: string;
    output_amount: number;
    /** How many minutes converting a piece takes during service */
    minutes: number;
}

/** The lists of a recipe: the ingredients and equipment it requires, and the ingredients it produces as byproducts */
export type RecipeComponentKind = ItemType | 'byproduct';

/** The lists of the recipe modal: the components, and the other meals the recipe takes */
export type RecipeListKind = RecipeComponentKind | 'meal';

/** The tabs of the recipe modal */
export type RecipeTab = 'details' | RecipeListKind;

/** The key each recipe component kind is listed under, both in the inventory state and when saving a recipe */
export const RECIPE_COMPONENT_KEYS: Record<RecipeComponentKind, 'ingredients' | 'equipment' | 'byproducts'> = {
    ingredient: 'ingredients',
    equipment:  'equipment',
    byproduct:  'byproducts',
};

/** The component kinds, which are saved under the keys of RECIPE_COMPONENT_KEYS */
export const RECIPE_COMPONENT_KINDS = Object.keys(RECIPE_COMPONENT_KEYS) as RecipeComponentKind[];

/** Every list of the recipe modal */
export const RECIPE_LIST_KINDS: RecipeListKind[] = [...RECIPE_COMPONENT_KINDS, 'meal'];

export interface Inventory {
    /** The restaurant's current date, as Y-m-d */
    date: string;
    storages: StorageSpace[];
    /** Ingredients brought in during service, waiting to be sorted into a storage space */
    pile: Item[];
    ingredients: IngredientType[];
    equipment: EquipmentType[];
    conversions: ConversionType[];
    recipes: RecipeType[];
}

/** The sidebar lists the storable item types, plus recipes and conversions */
export type SidebarTab = ItemType | 'recipe' | 'conversion';

export const TYPE_LABELS: Record<SidebarTab, string> = {
    ingredient: 'Ingredient',
    equipment:  'Equipment',
    recipe:     'Recipe',
    conversion: 'Conversion',
};

/** The edit modal of each sidebar tab, which the delete confirmation returns to when cancelled */
export const EDIT_MODALS: Record<SidebarTab, string> = {
    ingredient: 'ingredient-create-modal',
    equipment:  'equipment-create-modal',
    recipe:     'recipe-modal',
    conversion: 'conversion-modal',
};

export interface DraggedItem extends Item {
    from: string | null;
}

/** Drag data types, so item drags and storage reorder drags can be told apart during dragover */
export const ITEM_MIME = 'application/x-inventory-item';
export const STORAGE_MIME = 'application/x-inventory-storage';

/** Drop target keys for the unsaved general storage and the discard zone */
export const GENERAL = 'general';
export const DISCARD = 'discard';
/** Where items dragged out of the pile of ingredients brought in during service come from, as the server knows it */
export const PILE = 'pile';

/** The smallest fraction an ingredient can be moved or used in, matching the two decimals amounts are stored with */
export const MIN_DECIMAL_AMOUNT = 0.01;
