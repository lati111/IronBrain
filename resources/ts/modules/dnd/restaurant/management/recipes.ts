import {closeModal, openModal} from "../../../../components/modal";
import {formatMoney, refreshMoneyInputs, setField} from "../shared";
import {cloneTemplate, setFormValues, setIcon, setModalMode} from "../dom";
import {setAmountStep} from "./amount-modal";
import {
    findRecipe, getComponentCategory, getDefinition, getIcon, getIngredientCategories, getRecipeCategories,
    getRecipeTypes, postInventory, roundAmount, state,
} from "./inventory";
import {createListRow, hideRowActions} from "./sidebar";
import {
    CATEGORY_PREFIX, COOKER_CATEGORY, DEFAULT_EQUIPMENT_STAGE, EquipmentUsage, ItemType, RECIPE_COMPONENT_KEYS,
    RECIPE_COMPONENT_KINDS, RECIPE_LIST_KINDS, RECIPE_NUMBER_FIELDS, RecipeCategory, RecipeComponent, RecipeListKind,
    RecipeLists, RecipeMealRequirement, RecipeTab, RecipeType, TYPE_LABELS, VariantList,
} from "./types";

/** Recipes, and the recipe modal they are created and edited in. A recipe can be a variant of a base recipe, which
 * takes the base recipe's lists and adds to or takes away from them */

/** When each piece of equipment of the recipe open in the recipe modal is used, keyed by uuid */
const recipeEquipmentUsage = new Map<string, EquipmentUsage>();

/** The lists of the recipe open in the recipe modal, as amounts keyed by uuid. Ingredients taken by category are
 * listed with the ingredients, and meals of any recipe of a category with the meals, keyed by the category with
 * CATEGORY_PREFIX in front. Meals are counted in portions */
const recipeComponents: Record<RecipeListKind, Map<string, number>> = {
    ingredient: new Map(),
    equipment:  new Map(),
    byproduct:  new Map(),
    meal:       new Map(),
};

/** The base recipe of the variant open in the recipe modal, or null when it is no variant. A variant's lists show
 * its base recipe's entries, which can be taken away, followed by what it adds, which recipeComponents then holds */
let recipeBase: RecipeType | null = null;

/** What the variant open in the recipe modal takes away from its base recipe's lists, keyed as recipeComponents */
const recipeRemoved: Record<RecipeListKind, Set<string>> = {
    ingredient: new Set(),
    equipment:  new Set(),
    byproduct:  new Set(),
    meal:       new Set(),
};

//| Sidebar rows

/** Renders the sidebar rows for recipes, returning how many recipes exist before filtering */
export function renderRecipeRows(list: HTMLElement, search: string): number {
    // Variants are listed under their base recipe
    const recipes = state.inventory.recipes;
    const ordered = recipes.filter((recipe) => recipe.parent_uuid === null).flatMap((base) =>
        [base, ...recipes.filter((recipe) => recipe.parent_uuid === base.uuid)]);

    for (const recipe of ordered.filter((recipe) => recipe.name.toLowerCase().includes(search))) {
        const row = createListRow();
        row.classList.add('clickable');
        row.classList.toggle('recipe-variant-row', recipe.parent_uuid !== null);
        setIcon(row, null);
        setField(row, 'name', recipe.parent_uuid !== null ? `↳ ${recipe.name}` : recipe.name);
        setField(row, 'stored', formatMoney(recipe.price));
        row.querySelector('[data-field="stored"]')!.setAttribute('title', 'Price');
        row.title = `${recipe.name} — ${recipe.portions} portions, ${recipe.ingredients.length} ingredients, `
            + `${recipe.equipment.length} equipment, ${recipe.byproducts.length} byproducts`;

        // Recipes are never stored, so only opening them is possible
        hideRowActions(row, 'add', 'remove');
        row.addEventListener('click', () => openRecipeModal(recipe.uuid));

        list.appendChild(row);
    }

    return recipes.length;
}

//| Recipe modal

export function openRecipeModal(uuid: string | null): void {
    const recipe = uuid !== null ? findRecipe(uuid) : null;
    if (uuid !== null && recipe === null) return;

    state.editing.recipe = uuid;
    const form = document.getElementById('recipe-form') as HTMLFormElement;
    form.reset();

    fillRecipeTextOptions();
    fillRecipeParentSelect(recipe);
    recipeBase = recipe?.parent_uuid != null ? findRecipe(recipe.parent_uuid) : null;
    applyRecipeMode();

    // A variant shows what it changes, rather than the outcome
    const changes = recipe?.variant_changes ?? null;
    if (recipe !== null) {
        setFormValues(form, {
            name:        recipe.name,
            type:        recipe.type,
            category:    recipe.category ?? '',
            description: recipe.description ?? '',
            ...Object.fromEntries(RECIPE_NUMBER_FIELDS.map((field) =>
                [field, String(changes !== null ? changes.stats[field] : recipe[field])])),
        });
    }
    refreshMoneyInputs(form);

    fillRecipeLists(changes ?? recipe);
    clearRecipeRemoved();
    if (changes !== null) {
        changes.removed.ingredients.forEach((uuid) => recipeRemoved.ingredient.add(uuid));
        changes.removed.categories.forEach((category) => recipeRemoved.ingredient.add(CATEGORY_PREFIX + category));
        changes.removed.equipment.forEach((uuid) => recipeRemoved.equipment.add(uuid));
        changes.removed.byproducts.forEach((uuid) => recipeRemoved.byproduct.add(uuid));
        changes.removed.meals.forEach((key) => recipeRemoved.meal.add(key));
    }
    RECIPE_LIST_KINDS.forEach(renderRecipeComponents);

    switchRecipeTab('details');
    setModalMode('recipe', TYPE_LABELS.recipe, recipe !== null);
    openModal('recipe-modal');
}

/** Fills the recipe modal's lists from a recipe, or from what a variant adds, as they share the same shape */
function fillRecipeLists(source: RecipeLists | null): void {
    recipeEquipmentUsage.clear();
    source?.equipment.forEach((component) => recipeEquipmentUsage.set(component.uuid, {
        stage: component.stage ?? DEFAULT_EQUIPMENT_STAGE,
        container: component.container ?? false,
    }));

    for (const kind of RECIPE_LIST_KINDS) {
        recipeComponents[kind].clear();
        if (source !== null) {
            getListEntries(source, kind).forEach((amount, key) => recipeComponents[kind].set(key, amount));
        }
    }
}

function clearRecipeRemoved(): void {
    for (const kind of RECIPE_LIST_KINDS) {
        recipeRemoved[kind].clear();
    }
}

/** The entries of one of the lists of a recipe, or of what a variant adds to it, keyed as recipeComponents */
function getListEntries(source: RecipeLists, kind: RecipeListKind): Map<string, number> {
    const entries = new Map<string, number>();
    if (kind === 'meal') {
        source.meals.forEach((entry) => entries.set(entry.recipe_uuid ?? CATEGORY_PREFIX + entry.category, entry.portions));
        return entries;
    }

    source[RECIPE_COMPONENT_KEYS[kind]].forEach((component) => entries.set(component.uuid, component.amount));
    if (kind === 'ingredient') {
        source.categories.forEach((entry) => entries.set(CATEGORY_PREFIX + entry.category, entry.amount));
    }
    return entries;
}

/**
 * Offers the recipes a new recipe can be a variant of: those that are no variant themselves. Whether a recipe is a
 * variant is settled once it exists, so the choice is only shown for an existing one
 */
function fillRecipeParentSelect(recipe: RecipeType | null): void {
    const select = document.getElementById('recipe-parent-select') as HTMLSelectElement;
    select.replaceChildren(new Option('None', ''), ...state.inventory.recipes
        .filter((candidate) => candidate.parent_uuid === null && candidate.uuid !== recipe?.uuid)
        .map((candidate) => new Option(candidate.name, candidate.uuid)));

    select.value = recipe?.parent_uuid ?? '';
    select.disabled = recipe !== null;
}

/** Suggests the existing types and categories when entering a recipe's */
function fillRecipeTextOptions(): void {
    (document.getElementById('recipe-type-options') as HTMLDataListElement).replaceChildren(...getRecipeTypes().map((type) => new Option(type)));
    (document.getElementById('recipe-category-options') as HTMLDataListElement).replaceChildren(...getRecipeCategories().map((category) => new Option(category)));
}

/** Switches a new recipe between being a recipe of its own and a variant, which starts out as its base recipe */
export function changeRecipeParent(): void {
    const form = document.getElementById('recipe-form') as HTMLFormElement;
    recipeBase = findRecipe((document.getElementById('recipe-parent-select') as HTMLSelectElement).value);
    applyRecipeMode();

    // A variant's numbers are what it adds, so it starts out adding nothing
    for (const field of RECIPE_NUMBER_FIELDS) {
        const input = form.elements.namedItem(field) as HTMLInputElement;
        input.value = recipeBase !== null ? '0' : input.defaultValue;
    }
    refreshMoneyInputs(form);

    fillRecipeLists(null);
    clearRecipeRemoved();
    RECIPE_LIST_KINDS.forEach(renderRecipeComponents);
}

/**
 * Sets the recipe modal up for a variant or a recipe of its own. A variant takes its type and category from its
 * base recipe, and its numbers are added to its base recipe's, so they can also take away
 */
function applyRecipeMode(): void {
    const form = document.getElementById('recipe-form') as HTMLFormElement;
    const variant = recipeBase !== null;

    const typeRow = document.getElementById('recipe-type-row') as HTMLElement;
    typeRow.classList.toggle('hidden', variant);
    typeRow.querySelectorAll('input').forEach((input) => input.disabled = variant);

    for (const field of RECIPE_NUMBER_FIELDS) {
        const input = form.elements.namedItem(field) as HTMLInputElement;
        input.dataset.min ??= input.min;
        input.min = variant ? '' : input.dataset.min;
    }

    const hint = document.getElementById('recipe-variant-hint') as HTMLElement;
    hint.classList.toggle('hidden', !variant);
    hint.textContent = variant
        ? `Numbers are added to those of ${recipeBase!.name}. Its ingredients, meals and equipment are kept unless crossed out, and those added here come on top`
        : '';
}

export function switchRecipeTab(tab: RecipeTab): void {
    document.querySelectorAll<HTMLElement>('.recipe-tab').forEach((button) => {
        button.classList.toggle('active', button.dataset.recipeTab === tab);
    });
    document.querySelectorAll<HTMLElement>('.recipe-panel').forEach((panel) => {
        panel.classList.toggle('hidden', panel.dataset.recipePanel !== tab);
    });
}

//| Recipe lists

/** Byproducts are ingredients, so they are picked from and displayed as ingredients */
function getComponentItemType(kind: RecipeListKind): ItemType {
    return kind === 'equipment' ? 'equipment' : 'ingredient';
}

/** Names an entry of a recipe's list: an ingredient, equipment or recipe, or any of a category */
function describeRecipeEntry(kind: RecipeListKind, key: string): string {
    const category = getComponentCategory(key);
    if (category !== null) return `Any ${category}`;

    return kind === 'meal'
        ? findRecipe(key)?.name ?? 'Unknown'
        : getDefinition(getComponentItemType(kind), key)?.name ?? 'Unknown';
}

/** The icon of an entry of a recipe's list, which only specific ingredients have */
function getEntryIcon(kind: RecipeListKind, key: string): string | null {
    if (kind === 'meal' || getComponentCategory(key) !== null) return null;
    return getIcon(getDefinition(getComponentItemType(kind), key));
}

function renderRecipeComponents(kind: RecipeListKind): void {
    const list = document.getElementById(`recipe-${kind}-list`) as HTMLElement;
    const components = recipeComponents[kind];
    list.replaceChildren();

    // A variant lists its base recipe's entries first, which it keeps unless they are crossed out
    const base = recipeBase !== null ? getListEntries(recipeBase, kind) : new Map<string, number>();
    const removed = recipeRemoved[kind];
    for (const [key, amount] of base) {
        list.appendChild(createBaseEntryRow(kind, key, amount));
    }

    for (const [uuid, amount] of components) {
        list.appendChild(createEntryRow(kind, uuid, amount));
    }

    if (components.size === 0 && base.size === 0) {
        const empty = document.createElement('li');
        empty.className = 'text-gray-400 text-sm px-3 py-1';
        empty.textContent = 'None yet';
        list.appendChild(empty);
    }

    const count = components.size + [...base.keys()].filter((key) => !removed.has(key)).length;
    document.querySelector(`[data-recipe-count="${kind}"]`)!.textContent = count > 0 ? `(${count})` : '';

    fillRecipeComponentSelect(kind);
}

/** Creates the row of an entry of the base recipe of a variant, which can be crossed out to take it away */
function createBaseEntryRow(kind: RecipeListKind, key: string, amount: number): HTMLElement {
    const removed = recipeRemoved[kind];
    const row = cloneTemplate('recipe-component-template');
    row.classList.add('recipe-base-row');
    row.classList.toggle('removed', removed.has(key));
    setIcon(row, getEntryIcon(kind, key));
    setField(row, 'name', describeRecipeEntry(kind, key));

    const input = row.querySelector('[data-field="amount"]') as HTMLInputElement;
    input.value = String(amount);
    input.disabled = true;

    const toggle = row.querySelector('[data-action="remove"]') as HTMLElement;
    toggle.title = removed.has(key) ? 'Keep from the base recipe' : 'Take away from the base recipe';
    toggle.addEventListener('click', () => {
        if (!removed.delete(key)) removed.add(key);
        renderRecipeComponents(kind);
    });

    return row;
}

/** Creates the row of an entry of the recipe's own list, or of what a variant adds */
function createEntryRow(kind: RecipeListKind, uuid: string, amount: number): HTMLElement {
    const components = recipeComponents[kind];
    const row = cloneTemplate('recipe-component-template');
    setIcon(row, getEntryIcon(kind, uuid));
    // What a variant adds comes on top of its base recipe's
    setField(row, 'name', (recipeBase !== null ? '+ ' : '') + describeRecipeEntry(kind, uuid));

    // Ingredients can be used and produced in fractions, while equipment and meal portions come whole. Invalid
    // amounts are left for the form's own validation to report on save
    const decimals = getComponentItemType(kind) === 'ingredient' && kind !== 'meal';
    const input = row.querySelector('[data-field="amount"]') as HTMLInputElement;
    setAmountStep(input, decimals);
    input.value = String(amount);
    input.addEventListener('input', () => {
        const value = decimals ? roundAmount(parseFloat(input.value)) : parseInt(input.value, 10);
        if (!isNaN(value)) components.set(uuid, value);
    });

    if (kind === 'equipment') {
        input.before(createEquipmentUsageControls(uuid));
    }

    row.querySelector('[data-action="remove"]')!.addEventListener('click', () => {
        components.delete(uuid);
        recipeEquipmentUsage.delete(uuid);
        renderRecipeComponents(kind);
    });

    return row;
}

/**
 * Offers the types that are not part of a list yet. Cookers are where meals cook, so no recipe takes one, and a
 * recipe can't take a meal of itself. A variant can add more of what its base recipe takes
 */
function fillRecipeComponentSelect(kind: RecipeListKind): void {
    const components = recipeComponents[kind];
    const type = getComponentItemType(kind);
    const isMeal = kind === 'meal';

    const select = document.getElementById(`recipe-${kind}-select`) as HTMLSelectElement;
    const types: {uuid: string, name: string}[] = isMeal
        ? state.inventory.recipes.filter((recipe) => recipe.uuid !== state.editing.recipe)
        : type === 'ingredient'
            ? state.inventory.ingredients
            : state.inventory.equipment.filter((equipment) => equipment.category !== COOKER_CATEGORY);
    select.replaceChildren(...types
        .filter((definition) => !components.has(definition.uuid))
        .map((definition) => new Option(definition.name, definition.uuid)));

    // A recipe can also take any ingredient of a category, which is chosen when the meal is planned, or any meal of
    // a category of recipe
    if (kind === 'ingredient' || isMeal) {
        const group = document.createElement('optgroup');
        group.label = 'Categories';
        for (const category of isMeal ? getRecipeCategories() : getIngredientCategories()) {
            if (!components.has(CATEGORY_PREFIX + category)) {
                group.appendChild(new Option(`Any ${category}`, CATEGORY_PREFIX + category));
            }
        }
        if (group.children.length > 0) select.appendChild(group);
    }

    select.disabled = select.options.length === 0;
    if (select.disabled) {
        select.appendChild(new Option(isMeal ? 'No recipes left to add' : `No ${TYPE_LABELS[type].toLowerCase()} left to add`, ''));
    }
}

/**
 * Creates the controls for when a recipe's equipment is used, and whether it holds the meal as a container. The
 * stages come from a template rendered from the server, which also marks which stages can't hold a container
 */
function createEquipmentUsageControls(uuid: string): HTMLElement {
    const controls = cloneTemplate('equipment-usage-template');
    const stage = controls.querySelector('[data-field="stage"]') as HTMLSelectElement;
    const container = controls.querySelector('[data-field="container"]') as HTMLInputElement;
    const usage = recipeEquipmentUsage.get(uuid) ?? {stage: DEFAULT_EQUIPMENT_STAGE, container: false};
    recipeEquipmentUsage.set(uuid, usage);

    const allowsContainer = () => stage.selectedOptions[0]?.dataset.container === '1';
    const sync = () => {
        container.disabled = !allowsContainer();
        if (container.disabled) container.checked = false;
        recipeEquipmentUsage.set(uuid, {stage: stage.value, container: container.checked});
    };

    stage.value = usage.stage;
    container.checked = usage.container;
    stage.addEventListener('change', sync);
    container.addEventListener('change', sync);
    sync();

    return controls;
}

export function addRecipeComponent(kind: RecipeListKind): void {
    const select = document.getElementById(`recipe-${kind}-select`) as HTMLSelectElement;
    if (select.disabled || select.value === '') return;

    recipeComponents[kind].set(select.value, 1);
    renderRecipeComponents(kind);
}

//| Saving

export async function saveRecipe(): Promise<void> {
    const form = document.getElementById('recipe-form') as HTMLFormElement;

    // Browsers can't point at an invalid field inside a hidden tab, so open the tab of the first one first
    const invalid = form.querySelector<HTMLElement>(':invalid');
    const panel = invalid?.closest<HTMLElement>('[data-recipe-panel]');
    if (panel) {
        switchRecipeTab(panel.dataset.recipePanel as RecipeTab);
    }
    if (!form.reportValidity()) return;

    const editing = state.editing.recipe;
    const url = editing !== null ? `${state.apiUrl}/recipe/${editing}/edit` : `${state.apiUrl}/recipe/create`;

    // Sent as JSON, so an emptied component list still arrives as an empty list
    if (await postInventory(url, JSON.stringify(buildRecipePayload(form)), true) !== null) {
        state.editing.recipe = null;
        closeModal('recipe-modal');
    }
}

/** The recipe as it is saved: the form's fields, along with the lists of the recipe modal */
function buildRecipePayload(form: HTMLFormElement): Record<string, unknown> {
    const payload: Record<string, unknown> = Object.fromEntries(new FormData(form));
    payload.meals = Array.from(recipeComponents.meal, ([key, portions]): RecipeMealRequirement => {
        const category = getComponentCategory(key);
        return {recipe_uuid: category === null ? key : null, category, portions};
    });
    for (const kind of RECIPE_COMPONENT_KINDS) {
        const entries = Array.from(recipeComponents[kind]);
        payload[RECIPE_COMPONENT_KEYS[kind]] = entries
            .filter(([uuid]) => getComponentCategory(uuid) === null)
            .map(([uuid, amount]): RecipeComponent =>
                kind === 'equipment' ? {uuid, amount, ...recipeEquipmentUsage.get(uuid)} : {uuid, amount});

        if (kind === 'ingredient') {
            payload.categories = entries
                .filter(([key]) => getComponentCategory(key) !== null)
                .map(([key, amount]): RecipeCategory => ({category: getComponentCategory(key)!, amount}));
        }
    }

    // A variant's lists are what it adds, along with what it takes away from its base recipe's
    if (recipeBase !== null) {
        const removedIngredients = [...recipeRemoved.ingredient];
        payload.remove = {
            ingredients: removedIngredients.filter((key) => getComponentCategory(key) === null),
            categories: removedIngredients.map(getComponentCategory).filter((category): category is string => category !== null),
            equipment: [...recipeRemoved.equipment],
            byproducts: [...recipeRemoved.byproduct],
            meals: [...recipeRemoved.meal],
        } satisfies Record<VariantList, string[]>;
    }

    return payload;
}
