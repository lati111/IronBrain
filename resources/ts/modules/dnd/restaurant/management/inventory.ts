import {postOnce} from "../shared";
import {renderDate} from "./date";
import {renderSidebar} from "./sidebar";
import {renderGeneralStorage, renderPile, renderStorages} from "./storages";
import {
    CATEGORY_PREFIX, ConversionType, EquipmentType, IngredientType, Inventory, Item, ItemType, RecipeType, SidebarTab,
} from "./types";

/** The state of the management screen, shared by its parts */
export const state = {
    apiUrl: '',
    /** Players only move ingredients between the storage spaces and out of the pile */
    isPlayer: false,
    inventory: {date: '', storages: [], pile: [], ingredients: [], equipment: [], conversions: [], recipes: []} as Inventory,
    activeTab: 'ingredient' as SidebarTab,
    /** The uuid of what is open in the edit modal of each sidebar tab, or null when the modal is for something new */
    editing: {ingredient: null, equipment: null, recipe: null, conversion: null} as Record<SidebarTab, string | null>,
};

/** The unsaved general storage, keyed by "type:uuid:days left", so stacks of different freshness stay apart. Only
 * exists client side */
export const generalStorage = new Map<string, Item>();

export function setInventory(data: Inventory): void {
    state.inventory = data;

    // Drop unsaved items whose type no longer exists
    for (const [key, item] of generalStorage) {
        if (getDefinition(item.type, item.uuid) === null) {
            generalStorage.delete(key);
        }
    }

    render();
}

export function render(): void {
    renderDate();
    renderPile();
    renderStorages();

    // Players have neither the sidebar nor general storage
    if (!state.isPlayer) {
        renderSidebar();
        renderGeneralStorage();
    }
}

/**
 * Posts to an inventory endpoint and shows the updated inventory it returns
 * @param announce Whether to show the message the server responds with
 * @return The response data, or null when the request failed
 */
export async function postInventory(url: string, data: FormData | string = new FormData(), announce: boolean = false): Promise<any | null> {
    try {
        const response = await postOnce(url, data);
        if (!response?.ok) return null;
        if (announce) response.announce();

        setInventory(response.data.inventory);
        return response.data;
    } catch {
        // The error has already been shown to the user
        return null;
    }
}

//| Lookups

export function getDefinition(type: ItemType, uuid: string): IngredientType | EquipmentType | null {
    const types: (IngredientType | EquipmentType)[] = type === 'ingredient' ? state.inventory.ingredients : state.inventory.equipment;
    return types.find((definition) => definition.uuid === uuid) ?? null;
}

/** The icon of an ingredient, or null for equipment, which has none */
export function getIcon(definition: IngredientType | EquipmentType | null): string | null {
    return definition !== null && 'icon' in definition ? definition.icon : null;
}

export function findRecipe(uuid: string | null): RecipeType | null {
    return state.inventory.recipes.find((recipe) => recipe.uuid === uuid) ?? null;
}

export function findConversion(uuid: string): ConversionType | null {
    return state.inventory.conversions.find((conversion) => conversion.uuid === uuid) ?? null;
}

export function describe(type: ItemType, uuid: string): string {
    const definition = getDefinition(type, uuid);
    if (definition === null) return '';

    if ('expires_in' in definition) {
        return `${definition.name} — size ${definition.size}, expires in ${definition.expires_in} days`;
    }

    return `${definition.name} (${definition.category_label}) — size ${definition.size}`;
}

/** Describes a conversion, such as "Potato → 4 Fries" */
export function describeConversion(conversion: ConversionType): string {
    const input = getDefinition('ingredient', conversion.input_uuid)?.name ?? 'Unknown';
    const output = getDefinition('ingredient', conversion.output_uuid)?.name ?? 'Unknown';
    return `${input} → ${conversion.output_amount} ${output}`;
}

/** Every category the restaurant's ingredients fall under, alphabetically */
export function getIngredientCategories(): string[] {
    return sortedUnique(state.inventory.ingredients.map((ingredient) => ingredient.category));
}

/** Every category the restaurant's recipes fall under, alphabetically */
export function getRecipeCategories(): string[] {
    return sortedUnique(state.inventory.recipes.map((recipe) => recipe.category));
}

/** Every type of recipe the restaurant has, alphabetically */
export function getRecipeTypes(): string[] {
    return sortedUnique(state.inventory.recipes.map((recipe) => recipe.type));
}

/** The category of an entry of a recipe's ingredient or meal list, or null when it is a specific one */
export function getComponentCategory(key: string): string | null {
    return key.startsWith(CATEGORY_PREFIX) ? key.slice(CATEGORY_PREFIX.length) : null;
}

function sortedUnique(values: (string | null)[]): string[] {
    const present = values.filter((value): value is string => value !== null);
    return [...new Set(present)].sort((a, b) => a.localeCompare(b));
}

//| Amounts

export function itemKey(type: ItemType, uuid: string, daysLeft: number | null = null): string {
    return `${type}:${uuid}:${daysLeft ?? ''}`;
}

/** Rounds an amount to the two decimals amounts are stored with, so adding fractions doesn't drift (0.1 + 0.2) */
export function roundAmount(amount: number): number {
    return Math.round(amount * 100) / 100;
}
