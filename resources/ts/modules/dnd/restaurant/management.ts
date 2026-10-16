import {getData, toast} from "../../../main";
import {openModal, init as initModals, closeModal} from "../../../components/modal";
import {
    createReorderableList, EXPIRED_STORAGE_KEY, formatMoney, postOnce, refreshMoneyInputs, restoreSidebar, setField,
    setupDropTarget, setupMoneyInput, showWhileDragging, submitForm, toggleSidebar,
} from "./shared";

type ItemType = 'ingredient' | 'equipment';

interface Item {
    type: ItemType;
    uuid: string;
    amount: number;
    /** The stack an ingredient is stored in. Missing in general storage, which isn't saved */
    stack_uuid?: string;
    /** The days an ingredient has left before it expires, or null when it doesn't expire or is fresh */
    days_left?: number | null;
}

/** A stack of an ingredient that expired as the date moved on */
interface ExpiredStack {
    name: string;
    amount: number;
    location: string;
}

interface StorageSpace {
    uuid: string;
    name: string;
    capacity: number;
    used_capacity: number;
    expiration_multiplier: number;
    background_image: string | null;
    items: Item[];
}

interface IngredientType {
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

interface EquipmentType {
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

interface RecipeComponent {
    uuid: string;
    amount: number;
    /** For equipment: when during making the recipe it is used */
    stage?: string;
    /** For equipment: whether it holds the meal, staying in use for longer */
    container?: boolean;
}

/** When a recipe's equipment is used, and whether it holds the meal */
interface EquipmentUsage {
    stage: string;
    container: boolean;
}

/** The stage new equipment in a recipe starts at, matching the server's default */
const DEFAULT_EQUIPMENT_STAGE = 'prep';

/** The internal code of the cooker equipment category, which can't be part of a recipe */
const COOKER_CATEGORY = 'cooker';

interface RecipeType {
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
    ingredients: RecipeComponent[];
    equipment: RecipeComponent[];
    byproducts: RecipeComponent[];
    /** Ingredients taken by category, any ingredient of which will do */
    categories: RecipeCategory[];
    /** Other meals taken, of a specific recipe or of any recipe of a category */
    meals: RecipeMealRequirement[];
}

/** The lists a variant can add to or take away from, as the server knows them */
type VariantList = 'ingredients' | 'equipment' | 'byproducts' | 'categories' | 'meals';

/** What a variant changes about its base recipe */
interface VariantChanges {
    /** What is added to each of the base recipe's stats */
    stats: Record<typeof RECIPE_NUMBER_FIELDS[number], number>;
    /** What is added to the base recipe's lists, where adding what it has already adds to the amount */
    ingredients: RecipeComponent[];
    equipment: RecipeComponent[];
    byproducts: RecipeComponent[];
    categories: RecipeCategory[];
    meals: RecipeMealRequirement[];
    /** What is taken away from the base recipe's lists: by uuid, by category for ingredients taken by category, and
     * by recipe uuid or CATEGORY_PREFIX and category for meals */
    removed: Record<VariantList, string[]>;
}

interface RecipeMealRequirement {
    /** The recipe the meal must be of, or null when any recipe of the category will do */
    recipe_uuid: string | null;
    category: string | null;
    portions: number;
}

interface RecipeCategory {
    category: string;
    amount: number;
}

/** Marks an entry of a recipe's ingredient list as any ingredient of a category, rather than a specific one */
const CATEGORY_PREFIX = 'category:';

/** Turns an ingredient into another: each piece of the input becomes an amount of the output */
interface ConversionType {
    uuid: string;
    input_uuid: string;
    output_uuid: string;
    output_amount: number;
    /** How many minutes converting a piece takes during service */
    minutes: number;
}

/** The lists of a recipe: the ingredients and equipment it requires, and the ingredients it produces as byproducts */
type RecipeComponentKind = ItemType | 'byproduct';

/** The lists of the recipe modal: the components, and the other meals the recipe takes */
type RecipeListKind = RecipeComponentKind | 'meal';

/** The tabs of the recipe modal */
type RecipeTab = 'details' | RecipeListKind;

interface Inventory {
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

/** The sidebar lists the storable item types, plus recipes */
type SidebarTab = ItemType | 'recipe' | 'conversion';

interface DraggedItem extends Item {
    from: string | null;
}

/** Drag data types, so item drags and storage reorder drags can be told apart during dragover */
const ITEM_MIME = 'application/x-inventory-item';
const STORAGE_MIME = 'application/x-inventory-storage';

/** Drop target keys for the unsaved general storage and the discard zone */
const GENERAL = 'general';
const DISCARD = 'discard';
/** Where items dragged out of the pile of ingredients brought in during service come from, as the server knows it */
const PILE = 'pile';

const TYPE_LABELS: Record<SidebarTab, string> = {
    ingredient: 'Ingredient',
    equipment:  'Equipment',
    recipe:     'Recipe',
    conversion: 'Conversion',
};

/** The recipe fields that are plain numbers, in the same order as the form */
const RECIPE_NUMBER_FIELDS = [
    'price', 'portions', 'difficulty', 'prep_time', 'cooking_time', 'fresh_for', 'good_for', 'expires_in',
] as const;

/** The key each recipe component kind is listed under, both in the inventory state and when saving a recipe */
const RECIPE_COMPONENT_KEYS: Record<RecipeComponentKind, 'ingredients' | 'equipment' | 'byproducts'> = {
    ingredient: 'ingredients',
    equipment:  'equipment',
    byproduct:  'byproducts',
};

let apiUrl = '';
/** Players only move ingredients between the storage spaces and out of the pile */
let isPlayer = false;
let inventory: Inventory = {date: '', storages: [], pile: [], ingredients: [], equipment: [], conversions: [], recipes: []};
let activeTab: SidebarTab = 'ingredient';

/** The unsaved general storage, keyed by "type:uuid:days left", so stacks of different freshness stay apart. Only
 * exists client side */
const generalStorage = new Map<string, Item>();

let pendingAmountAction: ((amount: number) => Promise<void> | void) | null = null;
let pendingAmountMax: number | null = null;
let pendingAmountDecimals = false;

/** The smallest fraction an ingredient can be moved or used in, matching the two decimals amounts are stored with */
const MIN_DECIMAL_AMOUNT = 0.01;
let pendingDeleteUuid: string | null = null;
/** Makes a rendered storage space reorderable by dragging */
let registerStorageCard: (card: HTMLElement) => void = () => {};

/** The uuid of the ingredient or equipment currently being edited, or null when the create modal is for a new type */
let editingIngredientUuid: string | null = null;
let editingEquipmentUuid: string | null = null;
let editingRecipeUuid: string | null = null;
let editingConversionUuid: string | null = null;

/** The inventory item being dragged, as drop targets can't read drag data until the drop itself */
let draggedItem: DraggedItem | null = null;

/** The edit modal of each sidebar tab, which the delete confirmation returns to when cancelled */
const EDIT_MODALS: Record<SidebarTab, string> = {
    ingredient: 'ingredient-create-modal',
    equipment:  'equipment-create-modal',
    recipe:     'recipe-modal',
    conversion: 'conversion-modal',
};

/** What the delete confirmation for ingredients, equipment and recipes deletes when confirmed */
let pendingTypeDelete: {type: SidebarTab, uuid: string} | null = null;

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

/** The component kinds, which are saved under the keys of RECIPE_COMPONENT_KEYS */
const RECIPE_COMPONENT_KINDS = Object.keys(RECIPE_COMPONENT_KEYS) as RecipeComponentKind[];

/** Every list of the recipe modal */
const RECIPE_LIST_KINDS: RecipeListKind[] = [...RECIPE_COMPONENT_KINDS, 'meal'];

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

//| Initialisation

async function init(): Promise<void> {
    const root = document.getElementById('inventory') as HTMLElement;
    apiUrl = root.dataset.apiUrl!;
    isPlayer = root.dataset.player === '1';

    initModals();
    document.querySelectorAll<HTMLInputElement>('input[data-money]').forEach(setupMoneyInput);

    // Players neither add items, take them out, nor arrange the storage spaces
    if (!isPlayer) {
        setupItemDropTarget(document.getElementById('general-storage') as HTMLElement, GENERAL);
        // An empty general storage is hidden, though it still shows while an item is dragged so it can be dropped there
        showWhileDragging(document.getElementById('general-storage') as HTMLElement, ITEM_MIME);
        setupItemDropTarget(document.getElementById('discard-edge') as HTMLElement, DISCARD);
        showWhileDragging(document.getElementById('discard-edge') as HTMLElement, ITEM_MIME);
        registerStorageCard = createReorderableList(document.getElementById('storage-list') as HTMLElement, {
            mime: STORAGE_MIME,
            getSavedOrder: () => inventory.storages.map((storage) => storage.uuid),
            save: saveStorageOrder,
            restore: renderStorages,
        });
        restoreSidebar();
    }

    window.addEventListener('beforeunload', (event) => {
        if (generalStorage.size > 0) {
            event.preventDefault();
            event.returnValue = '';
        }
    });

    const response = await getData(apiUrl);
    setInventory(response.data);

    // Ingredients that expired when the service that just ended moved on to the next day
    const expired = sessionStorage.getItem(EXPIRED_STORAGE_KEY);
    if (expired !== null) {
        sessionStorage.removeItem(EXPIRED_STORAGE_KEY);
        showExpired(JSON.parse(expired) as ExpiredStack[]);
    }
}

function setInventory(data: Inventory): void {
    inventory = data;

    // Drop unsaved items whose type no longer exists
    for (const [key, item] of generalStorage) {
        if (getDefinition(item.type, item.uuid) === null) {
            generalStorage.delete(key);
        }
    }

    render();
}

//| Rendering

function render(): void {
    renderDate();
    renderPile();
    renderStorages();

    // Players have neither the sidebar nor general storage
    if (!isPlayer) {
        renderSidebar();
        renderGeneralStorage();
    }
}

function renderSidebar(): void {
    const list = document.getElementById('inventory-list') as HTMLElement;
    const search = (document.getElementById('inventory-search') as HTMLInputElement).value.trim().toLowerCase();

    list.replaceChildren();
    const total = activeTab === 'recipe'
        ? renderRecipeRows(list, search)
        : activeTab === 'conversion' ? renderConversionRows(list, search) : renderTypeRows(activeTab, list, search);

    if (list.childElementCount === 0) {
        const empty = document.createElement('li');
        empty.className = 'text-center text-gray-400 py-4 text-sm';
        empty.textContent = total === 0 ? `No ${TYPE_LABELS[activeTab].toLowerCase()} yet` : 'No results';
        list.appendChild(empty);
    }
}

function createListRow(): HTMLElement {
    const template = document.getElementById('list-row-template') as HTMLTemplateElement;
    return template.content.firstElementChild!.cloneNode(true) as HTMLElement;
}

/** Renders the sidebar rows for recipes, returning how many recipes exist before filtering */
function renderRecipeRows(list: HTMLElement, search: string): number {
    // Variants are listed under their base recipe
    const ordered = inventory.recipes.filter((recipe) => recipe.parent_uuid === null).flatMap((base) =>
        [base, ...inventory.recipes.filter((recipe) => recipe.parent_uuid === base.uuid)]);
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
        row.querySelector('[data-action="add"]')!.classList.add('hidden');
        row.querySelector('[data-action="remove"]')!.classList.add('hidden');
        row.addEventListener('click', () => openRecipeModal(recipe.uuid));

        list.appendChild(row);
    }

    return inventory.recipes.length;
}

/**
 * Renders the sidebar rows for conversions, returning how many conversions exist before filtering. A stored stack of
 * the input dropped on a conversion is converted
 */
function renderConversionRows(list: HTMLElement, search: string): number {
    for (const conversion of inventory.conversions.filter((conversion) => describeConversion(conversion).toLowerCase().includes(search))) {
        const row = createListRow();
        row.classList.add('clickable');
        setIcon(row, inventory.ingredients.find((ingredient) => ingredient.uuid === conversion.output_uuid)?.icon ?? null);
        setField(row, 'name', describeConversion(conversion));
        setField(row, 'stored', `${conversion.minutes} min`);
        row.querySelector('[data-field="stored"]')!.setAttribute('title', 'Minutes per piece during service');
        row.title = describeConversion(conversion);

        // Conversions are never stored, so only opening them is possible
        row.querySelector('[data-action="add"]')!.classList.add('hidden');
        row.querySelector('[data-action="remove"]')!.classList.add('hidden');
        row.querySelector('[data-action="edit"]')!.classList.add('hidden');
        row.addEventListener('click', () => openConversionModal(conversion.uuid));

        setupDropTarget(row, ITEM_MIME, (data) => openConvertModal(conversion, JSON.parse(data) as DraggedItem),
            () => draggedItem !== null && draggedItem.type === 'ingredient' && draggedItem.uuid === conversion.input_uuid);

        list.appendChild(row);
    }

    return inventory.conversions.length;
}

/** Describes a conversion, such as "Potato → 4 Fries" */
function describeConversion(conversion: ConversionType): string {
    const input = getDefinition('ingredient', conversion.input_uuid)?.name ?? 'Unknown';
    const output = getDefinition('ingredient', conversion.output_uuid)?.name ?? 'Unknown';
    return `${input} → ${conversion.output_amount} ${output}`;
}

/** Renders the sidebar rows for ingredients or equipment, returning how many types exist before filtering */
function renderTypeRows(itemType: ItemType, list: HTMLElement, search: string): number {
    const types: (IngredientType | EquipmentType)[] = itemType === 'ingredient' ? inventory.ingredients : inventory.equipment;
    const matches = types.filter((type) => {
        const haystack = 'category_label' in type ? `${type.name} ${type.category_label}` : type.name;
        return haystack.toLowerCase().includes(search);
    });

    for (const type of matches) {
        const row = createListRow();
        setIcon(row, 'icon' in type ? type.icon : null);
        setField(row, 'name', type.name);

        const unsaved = generalStorage.get(itemKey(itemType, type.uuid))?.amount ?? 0;
        setField(row, 'stored', unsaved > 0 ? `${type.stored} (+${unsaved})` : `${type.stored}`);
        row.title = describe(itemType, type.uuid);

        const unstoredTool = itemType === 'equipment' && (type as EquipmentType).requires_storage === false;

        row.querySelector('[data-action="edit"]')!.addEventListener('click', () => openEditTypeModal(itemType, type.uuid));

        const removeButton = row.querySelector('[data-action="remove"]') as HTMLElement;
        removeButton.classList.toggle('hidden', !unstoredTool || type.stored === 0);
        if (unstoredTool) {
            removeButton.addEventListener('click', () => {
                openAmountModal(`Remove ${type.name}`, 1, type.stored, (amount) => adjustOwned(type.uuid, -amount));
            });
        }

        const addButton = row.querySelector('[data-action="add"]')!;
        if (unstoredTool) {
            addButton.addEventListener('click', () => {
                openAmountModal(`Add ${type.name}`, 1, null, (amount) => adjustOwned(type.uuid, amount));
            });
        } else {
            addButton.addEventListener('click', () => {
                openAmountModal(`Add ${type.name} to general storage`, 1, null, (amount) => addToGeneral(itemType, type.uuid, amount), itemType === 'ingredient');
            });
        }

        list.appendChild(row);
    }

    return types.length;
}

/** Shows the ingredients brought in during service, which are moved out of the pile like out of a storage space */
function renderPile(): void {
    const section = document.getElementById('pile-storage') as HTMLElement;
    const container = section.querySelector('[data-items]') as HTMLElement;
    container.replaceChildren();

    for (const item of inventory.pile) {
        container.appendChild(createItemElement(item, PILE));
    }

    section.classList.toggle('hidden', inventory.pile.length === 0);
}

function renderGeneralStorage(): void {
    const container = document.querySelector('#general-storage [data-items]') as HTMLElement;
    container.replaceChildren();

    for (const item of sortItems([...generalStorage.values()])) {
        container.appendChild(createItemElement(item, null));
    }

    document.getElementById('general-storage')!.classList.toggle('empty', generalStorage.size === 0);
}

function renderStorages(): void {
    const list = document.getElementById('storage-list') as HTMLElement;
    const template = document.getElementById('storage-template') as HTMLTemplateElement;

    list.replaceChildren();
    for (const storage of inventory.storages) {
        const section = template.content.firstElementChild!.cloneNode(true) as HTMLElement;
        section.dataset.uuid = storage.uuid;

        if (storage.background_image !== null) {
            const background = section.querySelector('.inventory-storage-background') as HTMLElement;
            background.style.backgroundImage = `url(${JSON.stringify(storage.background_image)})`;
        }

        setField(section, 'name', storage.name);
        setField(section, 'occupancy', `${storage.used_capacity} / ${storage.capacity}`);

        const fill = section.querySelector('[data-field="capacity-fill"]') as HTMLElement;
        fill.style.width = `${Math.min(100, (storage.used_capacity / storage.capacity) * 100)}%`;
        fill.parentElement!.classList.toggle('full', storage.used_capacity >= storage.capacity);

        const items = section.querySelector('[data-items]') as HTMLElement;
        for (const item of storage.items) {
            items.appendChild(createItemElement(item, storage.uuid));
        }

        if (isPlayer) {
            section.draggable = false;
            section.querySelector('[data-action="delete"]')!.remove();
            section.querySelector('.inventory-storage-handle')?.remove();
        } else {
            section.querySelector('[data-action="delete"]')!.addEventListener('click', () => openDeleteStorageModal(storage));
        }
        setupItemDropTarget(section, storage.uuid);
        registerStorageCard(section);

        list.appendChild(section);
    }

    document.getElementById('storage-empty')!.classList.toggle('hidden', inventory.storages.length > 0);
}

function createItemElement(item: Item, from: string | null): HTMLElement {
    const template = document.getElementById('item-template') as HTMLTemplateElement;
    const element = template.content.firstElementChild!.cloneNode(true) as HTMLElement;
    const definition = getDefinition(item.type, item.uuid);

    element.dataset.type = item.type;
    element.title = describe(item.type, item.uuid);
    setIcon(element, definition !== null && 'icon' in definition ? definition.icon : null);
    setField(element, 'name', definition?.name ?? 'Unknown');
    setField(element, 'amount', `×${item.amount}`);
    setField(element, 'expiry', describeDaysLeft(item));
    element.classList.toggle('expiring', item.days_left !== undefined && item.days_left !== null && item.days_left <= 1);

    // Players only move stored ingredients
    if (isPlayer && (item.type !== 'ingredient' || from === null)) {
        element.draggable = false;
        return element;
    }

    element.addEventListener('dragstart', (event) => {
        const data: DraggedItem = {...item, from};
        draggedItem = data;
        event.dataTransfer!.setData(ITEM_MIME, JSON.stringify(data));
        event.dataTransfer!.effectAllowed = 'move';
    });
    element.addEventListener('dragend', () => draggedItem = null);

    return element;
}

function setIcon(root: HTMLElement, src: string | null): void {
    const icon = root.querySelector('[data-field="icon"]') as HTMLImageElement | null;
    if (icon === null) return;

    if (src === null) {
        icon.removeAttribute('src');
    } else {
        icon.src = src;
    }
}

//| Sidebar

function switchTab(tab: SidebarTab): void {
    activeTab = tab;

    document.querySelectorAll('.inventory-tab[data-tab]').forEach((button) => {
        button.classList.toggle('active', (button as HTMLElement).dataset.tab === tab);
    });

    document.getElementById('inventory-create-type')!.textContent = `+ New ${TYPE_LABELS[tab]}`;
    renderSidebar();
}

//| General storage

/**
 * Adds an item to the unsaved general storage, on the stack with the same days left
 * @param daysLeft The days an ingredient has left, or null when it is fresh or doesn't expire
 */
function addToGeneral(type: ItemType, uuid: string, amount: number, daysLeft: number | null = null): void {
    const key = itemKey(type, uuid, daysLeft);
    const existing = generalStorage.get(key);

    if (existing !== undefined) {
        existing.amount = roundAmount(existing.amount + amount);
    } else {
        generalStorage.set(key, {type, uuid, amount, days_left: daysLeft});
    }

    render();
}

function removeFromGeneral(type: ItemType, uuid: string, amount: number, daysLeft: number | null = null): void {
    const key = itemKey(type, uuid, daysLeft);
    const existing = generalStorage.get(key);
    if (existing === undefined) return;

    existing.amount = roundAmount(existing.amount - amount);
    if (existing.amount <= 0) {
        generalStorage.delete(key);
    }

    render();
}

//| Owned equipment (tools that do not require storage)

async function adjustOwned(uuid: string, amount: number): Promise<void> {
    const formData = new FormData();
    formData.append('amount', String(amount));

    try {
        const response = await postOnce(`${apiUrl}/equipment/${uuid}/owned`, formData);
        if (!response?.ok) return;

        setInventory(response.data.inventory);
    } catch {
        // The error has already been shown to the user
    }
}

//| Drag and drop: items

/** Shows the discard edge zone only while an inventory item is actively being dragged, without displacing layout */
function setupItemDropTarget(element: HTMLElement, target: string): void {
    element.addEventListener('dragover', (event) => {
        if (!event.dataTransfer!.types.includes(ITEM_MIME)) return;

        event.preventDefault();
        event.dataTransfer!.dropEffect = 'move';
        element.classList.add('drop-hover');
    });

    element.addEventListener('dragleave', (event) => {
        if (!element.contains(event.relatedTarget as Node | null)) {
            element.classList.remove('drop-hover');
        }
    });

    element.addEventListener('drop', (event) => {
        const raw = event.dataTransfer!.getData(ITEM_MIME);
        element.classList.remove('drop-hover');
        if (raw === '') return;

        event.preventDefault();
        event.stopPropagation();
        handleItemDrop(JSON.parse(raw) as DraggedItem, target);
    });
}

function handleItemDrop(item: DraggedItem, target: string): void {
    if ((item.from === null && target === GENERAL) || item.from === target) {
        return;
    }

    // A single piece, or a fraction of an ingredient, is moved whole without asking how much
    if (item.amount <= 1) {
        moveItem(item, item.amount, target);
        return;
    }

    const name = getDefinition(item.type, item.uuid)?.name ?? 'item';
    const title = target === DISCARD ? `Remove ${name}` : `Move ${name}`;
    openAmountModal(title, item.amount, item.amount, (amount) => moveItem(item, amount, target), item.type === 'ingredient');
}

async function moveItem(item: DraggedItem, amount: number, target: string): Promise<void> {
    // Unsaved items only need to be saved when they go into a storage space
    if (item.from === null) {
        if (target === DISCARD) {
            removeFromGeneral(item.type, item.uuid, amount, item.days_left ?? null);
            return;
        }

        if (await transfer(item, amount, null, target)) {
            removeFromGeneral(item.type, item.uuid, amount, item.days_left ?? null);
        }
        return;
    }

    const to = target === GENERAL || target === DISCARD ? null : target;
    if (await transfer(item, amount, item.from, to) && target === GENERAL) {
        addToGeneral(item.type, item.uuid, amount, item.days_left ?? null);
    }
}

async function transfer(item: Item, amount: number, from: string | null, to: string | null): Promise<boolean> {
    const formData = new FormData();
    formData.append('type', item.type);
    formData.append('item_uuid', item.uuid);
    formData.append('amount', String(amount));
    if (from !== null) formData.append('from_storage_uuid', from);
    if (to !== null) formData.append('to_storage_uuid', to);
    // A stored stack is moved as it is, while one from general storage brings the days it has left along
    if (item.stack_uuid !== undefined) formData.append('stack_uuid', item.stack_uuid);
    if (from === null && item.days_left !== undefined && item.days_left !== null) formData.append('days_left', String(item.days_left));

    try {
        const response = await postOnce(`${apiUrl}/transfer`, formData);
        if (!response?.ok) return false;

        setInventory(response.data.inventory);
        return true;
    } catch {
        // The error has already been shown to the user
        return false;
    }
}

//| Storage reordering

async function saveStorageOrder(order: string[]): Promise<void> {
    const formData = new FormData();
    order.forEach((uuid) => formData.append('order[]', uuid));

    try {
        const response = await postOnce(`${apiUrl}/storage/reorder`, formData);
        if (response?.ok) {
            setInventory(response.data.inventory);
            return;
        }
    } catch {
        // The error has already been shown to the user
    }

    renderStorages();
}

//| Storage spaces

function openCreateStorageModal(): void {
    (document.getElementById('storage-create-form') as HTMLFormElement).reset();
    openModal('storage-create-modal');
}

async function createStorage(): Promise<void> {
    const form = document.getElementById('storage-create-form') as HTMLFormElement;
    if (!form.reportValidity()) return;

    const response = await submitForm(form, `${apiUrl}/storage/create`, ['background_image']);
    if (response === null) return;

    closeModal('storage-create-modal');
    setInventory(response.inventory);
}

function openDeleteStorageModal(storage: StorageSpace): void {
    pendingDeleteUuid = storage.uuid;
    document.getElementById('storage-delete-name')!.textContent = storage.name;
    openModal('storage-delete-modal');
}

async function deleteStorage(): Promise<void> {
    if (pendingDeleteUuid === null) return;

    const uuid = pendingDeleteUuid;
    pendingDeleteUuid = null;
    closeModal('storage-delete-modal');

    try {
        const response = await postOnce(`${apiUrl}/storage/${uuid}/delete`, new FormData());
        if (!response?.ok) return;
        response.announce();

        setInventory(response.data.inventory);
        for (const item of response.data.released as Item[]) {
            addToGeneral(item.type, item.uuid, item.amount, item.days_left ?? null);
        }
    } catch {
        // The error has already been shown to the user
    }
}

//| Ingredient & equipment types

function openCreateTypeModal(): void {
    if (activeTab === 'recipe') {
        openRecipeModal(null);
        return;
    }

    if (activeTab === 'conversion') {
        openConversionModal(null);
        return;
    }

    if (activeTab === 'ingredient') {
        editingIngredientUuid = null;
        (document.getElementById('ingredient-create-form') as HTMLFormElement).reset();
        fillIngredientCategoryOptions();
        fillIngredientParentSelect(null);
        document.getElementById('ingredient-modal-title')!.textContent = 'New Ingredient';
        document.getElementById('ingredient-modal-submit')!.textContent = 'Create';
        document.getElementById('ingredient-modal-delete')!.classList.add('hidden');
        openModal('ingredient-create-modal');
        return;
    }

    editingEquipmentUuid = null;
    (document.getElementById('equipment-create-form') as HTMLFormElement).reset();
    fillEquipmentParentSelect(null);
    document.getElementById('equipment-modal-title')!.textContent = 'New Equipment';
    document.getElementById('equipment-modal-submit')!.textContent = 'Create';
    document.getElementById('equipment-modal-delete')!.classList.add('hidden');
    openModal('equipment-create-modal');
}

function openEditTypeModal(type: ItemType, uuid: string): void {
    if (type === 'ingredient') {
        const ingredient = inventory.ingredients.find((candidate) => candidate.uuid === uuid);
        if (ingredient === undefined) return;

        editingIngredientUuid = uuid;
        const form = document.getElementById('ingredient-create-form') as HTMLFormElement;
        form.reset();
        fillIngredientCategoryOptions();
        (form.elements.namedItem('name') as HTMLInputElement).value = ingredient.name;
        (form.elements.namedItem('category') as HTMLInputElement).value = ingredient.category ?? '';
        (form.elements.namedItem('expires_in') as HTMLInputElement).value = String(ingredient.expires_in);
        (form.elements.namedItem('size') as HTMLInputElement).value = String(ingredient.size);
        (form.elements.namedItem('quality') as HTMLInputElement).value = String(ingredient.quality);
        fillIngredientParentSelect(uuid);
        (form.elements.namedItem('parent_uuid') as HTMLSelectElement).value = ingredient.parent_uuid ?? '';

        document.getElementById('ingredient-modal-title')!.textContent = 'Edit Ingredient';
        document.getElementById('ingredient-modal-submit')!.textContent = 'Save';
        document.getElementById('ingredient-modal-delete')!.classList.remove('hidden');
        openModal('ingredient-create-modal');
        return;
    }

    const equipment = inventory.equipment.find((candidate) => candidate.uuid === uuid);
    if (equipment === undefined) return;

    editingEquipmentUuid = uuid;
    const form = document.getElementById('equipment-create-form') as HTMLFormElement;
    form.reset();
    (form.elements.namedItem('name') as HTMLInputElement).value = equipment.name;
    (form.elements.namedItem('category') as HTMLSelectElement).value = equipment.category;
    (form.elements.namedItem('description') as HTMLTextAreaElement).value = equipment.description ?? '';
    (form.elements.namedItem('size') as HTMLInputElement).value = String(equipment.size);
    (form.elements.namedItem('cleaning_time') as HTMLInputElement).value = String(equipment.cleaning_time);
    (form.elements.namedItem('multiplier') as HTMLInputElement).value = String(equipment.multiplier);
    // The checkbox shares its name with a hidden fallback field, so namedItem would return a RadioNodeList instead
    (form.querySelector('input[name="requires_storage"][type="checkbox"]') as HTMLInputElement).checked = equipment.requires_storage;

    fillEquipmentParentSelect(uuid);
    (form.elements.namedItem('parent_uuid') as HTMLSelectElement).value = equipment.parent_uuid ?? '';

    document.getElementById('equipment-modal-title')!.textContent = 'Edit Equipment';
    document.getElementById('equipment-modal-submit')!.textContent = 'Save';
    document.getElementById('equipment-modal-delete')!.classList.remove('hidden');
    openModal('equipment-create-modal');
}

/** Suggests the existing categories when entering an ingredient's category */
function fillIngredientCategoryOptions(): void {
    const list = document.getElementById('ingredient-category-options') as HTMLDataListElement;
    list.replaceChildren(...getIngredientCategories().map((category) => new Option(category)));
}

function fillIngredientParentSelect(excludeUuid: string | null): void {
    const select = document.getElementById('ingredient-parent-select') as HTMLSelectElement;
    select.replaceChildren(new Option('None', ''));
    for (const ingredient of inventory.ingredients) {
        if (ingredient.uuid === excludeUuid) continue; // an ingredient cannot be a variant of itself
        select.appendChild(new Option(ingredient.name, ingredient.uuid));
    }
}

function fillEquipmentParentSelect(excludeUuid: string | null): void {
    const select = document.getElementById('equipment-parent-select') as HTMLSelectElement;
    select.replaceChildren(new Option('None', ''));
    for (const equipment of inventory.equipment) {
        if (equipment.uuid === excludeUuid) continue; // an equipment cannot be its own parent
        select.appendChild(new Option(equipment.name, equipment.uuid));
    }
}

async function createIngredient(): Promise<void> {
    const form = document.getElementById('ingredient-create-form') as HTMLFormElement;
    if (!form.reportValidity()) return;

    const editing = editingIngredientUuid;
    const url = editing !== null ? `${apiUrl}/ingredient/${editing}/edit` : `${apiUrl}/ingredient/create`;

    const response = await submitForm(form, url, ['icon']);
    if (response === null) return;

    editingIngredientUuid = null;
    closeModal('ingredient-create-modal');
    setInventory(response.inventory);

    if (editing === null) {
        // Offer to add the new type straight away, as that is almost always the next step
        const name = getDefinition('ingredient', response.uuid)?.name ?? TYPE_LABELS['ingredient'];
        openAmountModal(`Add ${name} to general storage`, 1, null, (amount) => addToGeneral('ingredient', response.uuid, amount), true);
    }
}

async function createEquipment(): Promise<void> {
    const form = document.getElementById('equipment-create-form') as HTMLFormElement;
    if (!form.reportValidity()) return;

    const editing = editingEquipmentUuid;
    const url = editing !== null ? `${apiUrl}/equipment/${editing}/edit` : `${apiUrl}/equipment/create`;

    const response = await submitForm(form, url);
    if (response === null) return;

    editingEquipmentUuid = null;
    closeModal('equipment-create-modal');
    setInventory(response.inventory);

    if (editing === null) {
        // Offer to add the new type straight away, as that is almost always the next step
        const definition = getDefinition('equipment', response.uuid) as EquipmentType | null;
        const name = definition?.name ?? TYPE_LABELS['equipment'];
        if (definition !== null && definition.requires_storage === false) {
            openAmountModal(`Add ${name}`, 1, null, (amount) => adjustOwned(response.uuid, amount));
        } else {
            openAmountModal(`Add ${name} to general storage`, 1, null, (amount) => addToGeneral('equipment', response.uuid, amount));
        }
    }
}

//| Recipes

function openRecipeModal(uuid: string | null): void {
    const recipe = uuid !== null ? inventory.recipes.find((candidate) => candidate.uuid === uuid) ?? null : null;
    if (uuid !== null && recipe === null) return;

    editingRecipeUuid = uuid;
    const form = document.getElementById('recipe-form') as HTMLFormElement;
    form.reset();

    fillRecipeTextOptions();
    fillRecipeParentSelect(recipe);
    recipeBase = recipe?.parent_uuid != null ? inventory.recipes.find((candidate) => candidate.uuid === recipe.parent_uuid) ?? null : null;
    applyRecipeMode();

    // A variant shows what it changes, rather than the outcome
    const changes = recipe?.variant_changes ?? null;
    if (recipe !== null) {
        (form.elements.namedItem('name') as HTMLInputElement).value = recipe.name;
        (form.elements.namedItem('type') as HTMLInputElement).value = recipe.type;
        (form.elements.namedItem('category') as HTMLInputElement).value = recipe.category ?? '';
        (form.elements.namedItem('description') as HTMLTextAreaElement).value = recipe.description ?? '';
        for (const field of RECIPE_NUMBER_FIELDS) {
            (form.elements.namedItem(field) as HTMLInputElement).value = String(changes !== null ? changes.stats[field] : recipe[field]);
        }
    }
    refreshMoneyInputs(form);

    fillRecipeLists(changes ?? recipe);
    for (const kind of RECIPE_LIST_KINDS) {
        recipeRemoved[kind].clear();
    }
    if (changes !== null) {
        changes.removed.ingredients.forEach((uuid) => recipeRemoved.ingredient.add(uuid));
        changes.removed.categories.forEach((category) => recipeRemoved.ingredient.add(CATEGORY_PREFIX + category));
        changes.removed.equipment.forEach((uuid) => recipeRemoved.equipment.add(uuid));
        changes.removed.byproducts.forEach((uuid) => recipeRemoved.byproduct.add(uuid));
        changes.removed.meals.forEach((key) => recipeRemoved.meal.add(key));
    }
    RECIPE_LIST_KINDS.forEach(renderRecipeComponents);

    switchRecipeTab('details');
    document.getElementById('recipe-modal-title')!.textContent = recipe !== null ? 'Edit Recipe' : 'New Recipe';
    document.getElementById('recipe-modal-submit')!.textContent = recipe !== null ? 'Save' : 'Create';
    document.getElementById('recipe-modal-delete')!.classList.toggle('hidden', recipe === null);
    openModal('recipe-modal');
}

/** Fills the recipe modal's lists from a recipe, or from what a variant adds, as they share the same shape */
function fillRecipeLists(source: Pick<RecipeType, 'ingredients' | 'equipment' | 'byproducts' | 'categories' | 'meals'> | null): void {
    recipeEquipmentUsage.clear();
    source?.equipment.forEach((component) => recipeEquipmentUsage.set(component.uuid, {
        stage: component.stage ?? DEFAULT_EQUIPMENT_STAGE,
        container: component.container ?? false,
    }));

    for (const kind of RECIPE_COMPONENT_KINDS) {
        recipeComponents[kind].clear();
        source?.[RECIPE_COMPONENT_KEYS[kind]].forEach((component) => recipeComponents[kind].set(component.uuid, component.amount));
    }
    source?.categories.forEach((entry) => recipeComponents.ingredient.set(CATEGORY_PREFIX + entry.category, entry.amount));

    recipeComponents.meal.clear();
    source?.meals.forEach((entry) => recipeComponents.meal.set(entry.recipe_uuid ?? CATEGORY_PREFIX + entry.category, entry.portions));
}

/**
 * Offers the recipes a new recipe can be a variant of: those that are no variant themselves. Whether a recipe is a
 * variant is settled once it exists, so the choice is only shown for an existing one
 */
function fillRecipeParentSelect(recipe: RecipeType | null): void {
    const select = document.getElementById('recipe-parent-select') as HTMLSelectElement;
    select.replaceChildren(new Option('None', ''));
    for (const candidate of inventory.recipes) {
        if (candidate.parent_uuid === null && candidate.uuid !== recipe?.uuid) {
            select.appendChild(new Option(candidate.name, candidate.uuid));
        }
    }

    select.value = recipe?.parent_uuid ?? '';
    select.disabled = recipe !== null;
}

/** Switches a new recipe between being a recipe of its own and a variant, which starts out as its base recipe */
function changeRecipeParent(): void {
    const form = document.getElementById('recipe-form') as HTMLFormElement;
    const uuid = (document.getElementById('recipe-parent-select') as HTMLSelectElement).value;
    recipeBase = inventory.recipes.find((candidate) => candidate.uuid === uuid) ?? null;
    applyRecipeMode();

    // A variant's numbers are what it adds, so it starts out adding nothing
    for (const field of RECIPE_NUMBER_FIELDS) {
        const input = form.elements.namedItem(field) as HTMLInputElement;
        input.value = recipeBase !== null ? '0' : input.defaultValue;
    }
    refreshMoneyInputs(form);

    fillRecipeLists(null);
    for (const kind of RECIPE_LIST_KINDS) {
        recipeRemoved[kind].clear();
        renderRecipeComponents(kind);
    }
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

/** The entries of a list of the base recipe of the variant open in the recipe modal, keyed as recipeComponents */
function getBaseEntries(kind: RecipeListKind): Map<string, number> {
    const entries = new Map<string, number>();
    if (recipeBase === null) return entries;

    if (kind === 'meal') {
        recipeBase.meals.forEach((entry) => entries.set(entry.recipe_uuid ?? CATEGORY_PREFIX + entry.category, entry.portions));
        return entries;
    }

    recipeBase[RECIPE_COMPONENT_KEYS[kind]].forEach((component) => entries.set(component.uuid, component.amount));
    if (kind === 'ingredient') {
        recipeBase.categories.forEach((entry) => entries.set(CATEGORY_PREFIX + entry.category, entry.amount));
    }
    return entries;
}

/** Names an entry of a recipe's list: an ingredient, equipment or recipe, or any of a category */
function describeRecipeEntry(kind: RecipeListKind, key: string): string {
    const category = getComponentCategory(key);
    if (category !== null) return `Any ${category}`;

    return kind === 'meal'
        ? inventory.recipes.find((recipe) => recipe.uuid === key)?.name ?? 'Unknown'
        : getDefinition(getComponentItemType(kind), key)?.name ?? 'Unknown';
}

function switchRecipeTab(tab: RecipeTab): void {
    document.querySelectorAll<HTMLElement>('.recipe-tab').forEach((button) => {
        button.classList.toggle('active', button.dataset.recipeTab === tab);
    });
    document.querySelectorAll<HTMLElement>('.recipe-panel').forEach((panel) => {
        panel.classList.toggle('hidden', panel.dataset.recipePanel !== tab);
    });
}

/** Byproducts are ingredients, so they are picked from and displayed as ingredients */
function getComponentItemType(kind: RecipeListKind): ItemType {
    return kind === 'equipment' ? 'equipment' : 'ingredient';
}

function renderRecipeComponents(kind: RecipeListKind): void {
    const list = document.getElementById(`recipe-${kind}-list`) as HTMLElement;
    const template = document.getElementById('recipe-component-template') as HTMLTemplateElement;
    const components = recipeComponents[kind];
    const type = getComponentItemType(kind);
    const isMeal = kind === 'meal';

    list.replaceChildren();

    // A variant lists its base recipe's entries first, which it keeps unless they are crossed out
    const base = getBaseEntries(kind);
    const removed = recipeRemoved[kind];
    for (const [key, amount] of base) {
        const definition = getComponentCategory(key) === null && !isMeal ? getDefinition(type, key) : null;
        const row = template.content.firstElementChild!.cloneNode(true) as HTMLElement;
        row.classList.add('recipe-base-row');
        row.classList.toggle('removed', removed.has(key));
        setIcon(row, definition !== null && 'icon' in definition ? definition.icon : null);
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

        list.appendChild(row);
    }

    for (const [uuid, amount] of components) {
        const category = getComponentCategory(uuid);
        const definition = category === null && !isMeal ? getDefinition(type, uuid) : null;
        const row = template.content.firstElementChild!.cloneNode(true) as HTMLElement;
        setIcon(row, definition !== null && 'icon' in definition ? definition.icon : null);
        // What a variant adds comes on top of its base recipe's
        setField(row, 'name', (recipeBase !== null ? '+ ' : '') + describeRecipeEntry(kind, uuid));

        // Ingredients can be used and produced in fractions, while equipment and meal portions come whole. Invalid
        // amounts are left for the form's own validation to report on save
        const decimals = type === 'ingredient' && !isMeal;
        const input = row.querySelector('[data-field="amount"]') as HTMLInputElement;
        input.min = decimals ? String(MIN_DECIMAL_AMOUNT) : '1';
        input.step = decimals ? String(MIN_DECIMAL_AMOUNT) : '1';
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

        list.appendChild(row);
    }

    if (components.size === 0 && base.size === 0) {
        const empty = document.createElement('li');
        empty.className = 'text-gray-400 text-sm px-3 py-1';
        empty.textContent = 'None yet';
        list.appendChild(empty);
    }

    const count = components.size + [...base.keys()].filter((key) => !removed.has(key)).length;
    document.querySelector(`[data-recipe-count="${kind}"]`)!.textContent = count > 0 ? `(${count})` : '';

    // Only offer the types that are not part of the list yet. Cookers are where meals cook, so no recipe takes one,
    // and a recipe can't take a meal of itself. A variant can add more of what its base recipe takes
    const select = document.getElementById(`recipe-${kind}-select`) as HTMLSelectElement;
    const types: {uuid: string, name: string}[] = isMeal
        ? inventory.recipes.filter((recipe) => recipe.uuid !== editingRecipeUuid)
        : type === 'ingredient'
            ? inventory.ingredients
            : inventory.equipment.filter((equipment) => equipment.category !== COOKER_CATEGORY);
    select.replaceChildren();
    for (const definition of types) {
        if (!components.has(definition.uuid)) {
            select.appendChild(new Option(definition.name, definition.uuid));
        }
    }

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
    const template = document.getElementById('equipment-usage-template') as HTMLTemplateElement;
    const controls = template.content.firstElementChild!.cloneNode(true) as HTMLElement;
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

/** The category of an entry of a recipe's ingredient list, or null when it is a specific ingredient */
function getComponentCategory(key: string): string | null {
    return key.startsWith(CATEGORY_PREFIX) ? key.slice(CATEGORY_PREFIX.length) : null;
}

/** Every category the restaurant's ingredients fall under, alphabetically */
function getIngredientCategories(): string[] {
    const categories = inventory.ingredients
        .map((ingredient) => ingredient.category)
        .filter((category): category is string => category !== null);

    return [...new Set(categories)].sort((a, b) => a.localeCompare(b));
}

/** Every category the restaurant's recipes fall under, alphabetically */
function getRecipeCategories(): string[] {
    const categories = inventory.recipes
        .map((recipe) => recipe.category)
        .filter((category): category is string => category !== null);

    return [...new Set(categories)].sort((a, b) => a.localeCompare(b));
}

/** Suggests the existing types and categories when entering a recipe's */
function fillRecipeTextOptions(): void {
    const types = [...new Set(inventory.recipes.map((recipe) => recipe.type))].sort((a, b) => a.localeCompare(b));
    (document.getElementById('recipe-type-options') as HTMLDataListElement).replaceChildren(...types.map((type) => new Option(type)));
    (document.getElementById('recipe-category-options') as HTMLDataListElement).replaceChildren(...getRecipeCategories().map((category) => new Option(category)));
}

function addRecipeComponent(kind: RecipeListKind): void {
    const select = document.getElementById(`recipe-${kind}-select`) as HTMLSelectElement;
    if (select.disabled || select.value === '') return;

    recipeComponents[kind].set(select.value, 1);
    renderRecipeComponents(kind);
}

async function saveRecipe(): Promise<void> {
    const form = document.getElementById('recipe-form') as HTMLFormElement;

    // Browsers can't point at an invalid field inside a hidden tab, so open the tab of the first one first
    const invalid = form.querySelector<HTMLElement>(':invalid');
    const panel = invalid?.closest<HTMLElement>('[data-recipe-panel]');
    if (panel) {
        switchRecipeTab(panel.dataset.recipePanel as RecipeTab);
    }
    if (!form.reportValidity()) return;

    const editing = editingRecipeUuid;
    const url = editing !== null ? `${apiUrl}/recipe/${editing}/edit` : `${apiUrl}/recipe/create`;

    // Sent as JSON, so an emptied component list still arrives as an empty list
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

    try {
        const response = await postOnce(url, JSON.stringify(payload));
        if (!response?.ok) return;
        response.announce();

        editingRecipeUuid = null;
        closeModal('recipe-modal');
        setInventory(response.data.inventory);
    } catch {
        // The error has already been shown to the user
    }
}

//| Conversions

function openConversionModal(uuid: string | null): void {
    const conversion = uuid !== null ? inventory.conversions.find((candidate) => candidate.uuid === uuid) ?? null : null;
    if (uuid !== null && conversion === null) return;

    editingConversionUuid = uuid;
    const form = document.getElementById('conversion-form') as HTMLFormElement;
    form.reset();

    for (const name of ['input_uuid', 'output_uuid']) {
        const select = form.elements.namedItem(name) as HTMLSelectElement;
        select.replaceChildren(...inventory.ingredients.map((ingredient) => new Option(ingredient.name, ingredient.uuid)));
    }

    if (conversion !== null) {
        (form.elements.namedItem('input_uuid') as HTMLSelectElement).value = conversion.input_uuid;
        (form.elements.namedItem('output_uuid') as HTMLSelectElement).value = conversion.output_uuid;
        (form.elements.namedItem('output_amount') as HTMLInputElement).value = String(conversion.output_amount);
        (form.elements.namedItem('minutes') as HTMLInputElement).value = String(conversion.minutes);
    } else if (inventory.ingredients.length > 1) {
        // A conversion needs two different ingredients, so the output starts on another one than the input
        (form.elements.namedItem('output_uuid') as HTMLSelectElement).selectedIndex = 1;
    }

    document.getElementById('conversion-modal-title')!.textContent = conversion !== null ? 'Edit Conversion' : 'New Conversion';
    document.getElementById('conversion-modal-submit')!.textContent = conversion !== null ? 'Save' : 'Create';
    document.getElementById('conversion-modal-delete')!.classList.toggle('hidden', conversion === null);
    openModal('conversion-modal');
}

async function saveConversion(): Promise<void> {
    const form = document.getElementById('conversion-form') as HTMLFormElement;
    if (!form.reportValidity()) return;

    const editing = editingConversionUuid;
    const url = editing !== null ? `${apiUrl}/conversion/${editing}/edit` : `${apiUrl}/conversion/create`;

    const response = await submitForm(form, url);
    if (response === null) return;

    editingConversionUuid = null;
    closeModal('conversion-modal');
    setInventory(response.inventory);
}

/** Asks how much of a dragged stack to convert, at most all of it */
function openConvertModal(conversion: ConversionType, item: DraggedItem): void {
    const input = getDefinition('ingredient', conversion.input_uuid)?.name ?? 'ingredient';
    const output = getDefinition('ingredient', conversion.output_uuid)?.name ?? 'ingredient';
    openAmountModal(`Convert ${input} into ${output}`, item.amount, item.amount, (amount) => convertItem(conversion, item, amount), true);
}

/**
 * Converts part of a stack, with what it becomes going where the stack is. Stacks in the unsaved general storage are
 * converted there, while stored ones are converted by the server
 */
async function convertItem(conversion: ConversionType, item: DraggedItem, amount: number): Promise<void> {
    if (item.from === null) {
        removeFromGeneral('ingredient', item.uuid, amount, item.days_left ?? null);
        addToGeneral('ingredient', conversion.output_uuid, roundAmount(amount * conversion.output_amount));
        return;
    }

    const formData = new FormData();
    formData.append('amount', String(amount));
    formData.append('from_storage_uuid', item.from);
    if (item.stack_uuid !== undefined) formData.append('stack_uuid', item.stack_uuid);

    try {
        const response = await postOnce(`${apiUrl}/conversion/${conversion.uuid}/convert`, formData);
        if (!response?.ok) return;

        setInventory(response.data.inventory);
    } catch {
        // The error has already been shown to the user
    }
}

//| Deleting ingredients, equipment & recipes

function getEditingUuid(type: SidebarTab): string | null {
    switch (type) {
        case 'ingredient': return editingIngredientUuid;
        case 'equipment':  return editingEquipmentUuid;
        case 'recipe':     return editingRecipeUuid;
        case 'conversion': return editingConversionUuid;
    }
}

function openDeleteTypeModal(type: SidebarTab): void {
    const uuid = getEditingUuid(type);
    if (uuid === null) return;

    const conversion = inventory.conversions.find((candidate) => candidate.uuid === uuid);
    const name = type === 'recipe'
        ? inventory.recipes.find((recipe) => recipe.uuid === uuid)?.name
        : type === 'conversion'
            ? (conversion !== undefined ? describeConversion(conversion) : undefined)
            : getDefinition(type, uuid)?.name;

    pendingTypeDelete = {type, uuid};
    document.getElementById('type-delete-title')!.textContent = `Delete ${TYPE_LABELS[type].toLowerCase()}`;
    document.getElementById('type-delete-name')!.textContent = name ?? TYPE_LABELS[type];

    // The edit modal stays filled in, so cancelling can return to it as it was
    closeModal(EDIT_MODALS[type]);
    openModal('type-delete-modal');
}

function cancelDeleteType(): void {
    closeModal('type-delete-modal');
    if (pendingTypeDelete !== null) {
        openModal(EDIT_MODALS[pendingTypeDelete.type]);
        pendingTypeDelete = null;
    }
}

async function deleteType(): Promise<void> {
    if (pendingTypeDelete === null) return;

    const {type, uuid} = pendingTypeDelete;
    pendingTypeDelete = null;
    closeModal('type-delete-modal');

    try {
        const response = await postOnce(`${apiUrl}/${type}/${uuid}/delete`, new FormData());
        if (!response?.ok) return;
        response.announce();

        if (type === 'ingredient') editingIngredientUuid = null;
        if (type === 'equipment') editingEquipmentUuid = null;
        if (type === 'recipe') editingRecipeUuid = null;
        if (type === 'conversion') editingConversionUuid = null;

        // Also drops any unsaved general storage stacks of a deleted ingredient or equipment
        setInventory(response.data.inventory);
    } catch {
        // The error has already been shown to the user
    }
}

//| Amount modal

/**
 * Asks for an amount before performing an action with it
 * @param decimals Whether fractions such as 0.5 are allowed, as for ingredients. Equipment only comes in whole pieces
 */
function openAmountModal(title: string, amount: number, max: number | null, action: (amount: number) => Promise<void> | void, decimals: boolean = false): void {
    pendingAmountAction = action;
    pendingAmountMax = max;
    pendingAmountDecimals = decimals;

    document.getElementById('amount-modal-title')!.textContent = title;
    const input = document.getElementById('amount-modal-input') as HTMLInputElement;
    input.min = decimals ? String(MIN_DECIMAL_AMOUNT) : '1';
    input.step = decimals ? String(MIN_DECIMAL_AMOUNT) : '1';
    input.value = String(amount);
    input.max = max !== null ? String(max) : '';

    openModal('amount-modal');
    input.focus();
    input.select();
}

async function confirmAmount(): Promise<void> {
    const amount = roundAmount(parseFloat((document.getElementById('amount-modal-input') as HTMLInputElement).value));
    const min = pendingAmountDecimals ? MIN_DECIMAL_AMOUNT : 1;
    const whole = pendingAmountDecimals || Number.isInteger(amount);

    if (isNaN(amount) || amount < min || !whole || (pendingAmountMax !== null && amount > pendingAmountMax)) {
        const kind = pendingAmountDecimals ? 'an amount' : 'a whole amount';
        toast(pendingAmountMax !== null ? `Enter ${kind} between ${min} and ${pendingAmountMax}` : `Enter ${kind} of at least ${min}`);
        return;
    }

    const action = pendingAmountAction;
    pendingAmountAction = null;
    closeModal('amount-modal');

    if (action !== null) {
        await action(amount);
    }
}

//| Helpers

function itemKey(type: ItemType, uuid: string, daysLeft: number | null = null): string {
    return `${type}:${uuid}:${daysLeft ?? ''}`;
}

/**
 * Describes how long an ingredient stack has left, such as "4d", or nothing when it doesn't expire. Fresh stacks
 * in general storage count their ingredient's full lifespan
 */
function describeDaysLeft(item: Item): string {
    if (item.type !== 'ingredient') return '';

    const definition = getDefinition('ingredient', item.uuid) as IngredientType | null;
    if (definition === null || definition.expires_in <= 0) return '';

    return `${item.days_left ?? definition.expires_in}d`;
}

/** Sorts items by name, and stacks of the same ingredient by the days they have left, soonest to expire first */
function sortItems(items: Item[]): Item[] {
    const name = (item: Item) => (getDefinition(item.type, item.uuid)?.name ?? '').toLowerCase();
    return items.sort((a, b) => name(a).localeCompare(name(b)) || (a.days_left ?? Infinity) - (b.days_left ?? Infinity));
}

//| Date

function renderDate(): void {
    if (inventory.date === '') return;

    const date = new Date(`${inventory.date}T00:00:00`);
    document.getElementById('inventory-date')!.textContent = date.toLocaleDateString(undefined, {day: 'numeric', month: 'long', year: 'numeric'});
}

/** Moves the date a day forward, which ages the stored ingredients, and shows any that expired */
async function advanceDay(): Promise<void> {
    try {
        const response = await postOnce(`${apiUrl}/advance-day`, new FormData());
        if (!response?.ok) return;

        setInventory(response.data.inventory);
        showExpired(response.data.expired as ExpiredStack[]);
    } catch {
        // The error has already been shown to the user
    }
}

function showExpired(expired: ExpiredStack[]): void {
    if (expired.length === 0) return;

    const list = document.getElementById('expired-items') as HTMLElement;
    list.replaceChildren();
    for (const stack of expired) {
        const row = document.createElement('li');
        row.className = 'order-details-item';

        const name = document.createElement('span');
        name.className = 'flex-1 truncate';
        name.textContent = `${stack.name} ×${stack.amount}`;

        const location = document.createElement('span');
        location.className = 'text-gray-500';
        location.textContent = stack.location;

        row.append(name, location);
        list.appendChild(row);
    }

    openModal('expired-modal');
}

/** Rounds an amount to the two decimals amounts are stored with, so adding fractions doesn't drift (0.1 + 0.2) */
function roundAmount(amount: number): number {
    return Math.round(amount * 100) / 100;
}

function getDefinition(type: ItemType, uuid: string): IngredientType | EquipmentType | null {
    const types: (IngredientType | EquipmentType)[] = type === 'ingredient' ? inventory.ingredients : inventory.equipment;
    return types.find((definition) => definition.uuid === uuid) ?? null;
}

function describe(type: ItemType, uuid: string): string {
    const definition = getDefinition(type, uuid);
    if (definition === null) return '';

    if ('expires_in' in definition) {
        return `${definition.name} — size ${definition.size}, expires in ${definition.expires_in} days`;
    }

    return `${definition.name} (${definition.category_label}) — size ${definition.size}`;
}

(<any>window).init                   = init;
(<any>window).renderSidebar          = renderSidebar;
(<any>window).toggleSidebar          = toggleSidebar;
(<any>window).switchTab              = switchTab;
(<any>window).openCreateStorageModal = openCreateStorageModal;
(<any>window).createStorage          = createStorage;
(<any>window).deleteStorage          = deleteStorage;
(<any>window).openCreateTypeModal    = openCreateTypeModal;
(<any>window).createIngredient       = createIngredient;
(<any>window).createEquipment        = createEquipment;
(<any>window).saveRecipe             = saveRecipe;
(<any>window).changeRecipeParent     = changeRecipeParent;
(<any>window).saveConversion         = saveConversion;
(<any>window).addRecipeComponent     = addRecipeComponent;
(<any>window).switchRecipeTab        = switchRecipeTab;
(<any>window).openDeleteTypeModal    = openDeleteTypeModal;
(<any>window).cancelDeleteType       = cancelDeleteType;
(<any>window).deleteType             = deleteType;
(<any>window).confirmAmount          = confirmAmount;
(<any>window).advanceDay = advanceDay;
(<any>window).openModal              = openModal;
(<any>window).closeModal             = closeModal;
