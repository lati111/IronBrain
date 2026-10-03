import {getData, postData, toast} from "../../../main";
import {openModal, init as initModals, closeModal} from "../../../components/modal";

type ItemType = 'ingredient' | 'equipment';

interface Item {
    type: ItemType;
    uuid: string;
    amount: number;
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
    icon: string | null;
    size: number;
    expires_in: number;
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
}

interface RecipeType {
    uuid: string;
    name: string;
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
}

/** The lists of a recipe: the ingredients and equipment it requires, and the ingredients it produces as byproducts */
type RecipeComponentKind = ItemType | 'byproduct';

/** The tabs of the recipe modal */
type RecipeTab = 'details' | RecipeComponentKind;

interface Inventory {
    storages: StorageSpace[];
    ingredients: IngredientType[];
    equipment: EquipmentType[];
    recipes: RecipeType[];
}

/** The sidebar lists the storable item types, plus recipes */
type SidebarTab = ItemType | 'recipe';

interface DraggedItem extends Item {
    from: string | null;
}

/** Drag data types, so item drags and storage reorder drags can be told apart during dragover */
const ITEM_MIME = 'application/x-inventory-item';
const STORAGE_MIME = 'application/x-inventory-storage';

/** Drop target keys for the unsaved general storage and the discard zone */
const GENERAL = 'general';
const DISCARD = 'discard';

const TYPE_LABELS: Record<SidebarTab, string> = {
    ingredient: 'Ingredient',
    equipment:  'Equipment',
    recipe:     'Recipe',
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
let inventory: Inventory = {storages: [], ingredients: [], equipment: [], recipes: []};
let activeTab: SidebarTab = 'ingredient';

/** The unsaved general storage, keyed by "type:uuid". Only exists client side */
const generalStorage = new Map<string, Item>();

let pendingAmountAction: ((amount: number) => Promise<void> | void) | null = null;
let pendingAmountMax: number | null = null;
let pendingDeleteUuid: string | null = null;
let draggedStorage: HTMLElement | null = null;

/** The uuid of the ingredient or equipment currently being edited, or null when the create modal is for a new type */
let editingIngredientUuid: string | null = null;
let editingEquipmentUuid: string | null = null;
let editingRecipeUuid: string | null = null;

/** The edit modal of each sidebar tab, which the delete confirmation returns to when cancelled */
const EDIT_MODALS: Record<SidebarTab, string> = {
    ingredient: 'ingredient-create-modal',
    equipment:  'equipment-create-modal',
    recipe:     'recipe-modal',
};

/** What the delete confirmation for ingredients, equipment and recipes deletes when confirmed */
let pendingTypeDelete: {type: SidebarTab, uuid: string} | null = null;

/** The component lists of the recipe open in the recipe modal, as amounts keyed by uuid */
const recipeComponents: Record<RecipeComponentKind, Map<string, number>> = {
    ingredient: new Map(),
    equipment:  new Map(),
    byproduct:  new Map(),
};

//| Initialisation

async function init(): Promise<void> {
    apiUrl = (document.getElementById('inventory') as HTMLElement).dataset.apiUrl!;

    initModals();
    setupItemDropTarget(document.getElementById('general-storage') as HTMLElement, GENERAL);
    setupItemDropTarget(document.getElementById('discard-edge') as HTMLElement, DISCARD);
    setupStorageReordering(document.getElementById('storage-list') as HTMLElement);
    setupDiscardEdgeVisibility();

    if (localStorage.getItem('dnd-restaurant-sidebar-collapsed') === '1') {
        document.getElementById('inventory-sidebar')!.classList.add('collapsed');
    }

    window.addEventListener('beforeunload', (event) => {
        if (generalStorage.size > 0) {
            event.preventDefault();
            event.returnValue = '';
        }
    });

    const response = await getData(apiUrl);
    setInventory(response.data);
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
    renderSidebar();
    renderGeneralStorage();
    renderStorages();
}

function renderSidebar(): void {
    const list = document.getElementById('inventory-list') as HTMLElement;
    const search = (document.getElementById('inventory-search') as HTMLInputElement).value.trim().toLowerCase();

    list.replaceChildren();
    const total = activeTab === 'recipe' ? renderRecipeRows(list, search) : renderTypeRows(activeTab, list, search);

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
    for (const recipe of inventory.recipes.filter((recipe) => recipe.name.toLowerCase().includes(search))) {
        const row = createListRow();
        row.classList.add('clickable');
        setIcon(row, null);
        setField(row, 'name', recipe.name);
        setField(row, 'stored', `${recipe.price}`);
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
                openAmountModal(`Add ${type.name} to general storage`, 1, null, (amount) => addToGeneral(itemType, type.uuid, amount));
            });
        }

        list.appendChild(row);
    }

    return types.length;
}

function renderGeneralStorage(): void {
    const container = document.querySelector('#general-storage [data-items]') as HTMLElement;
    container.replaceChildren();

    for (const item of generalStorage.values()) {
        container.appendChild(createItemElement(item, null));
    }

    if (generalStorage.size === 0) {
        const empty = document.createElement('p');
        empty.className = 'text-gray-400 text-sm m-auto';
        empty.textContent = 'Add items from the list on the left';
        container.appendChild(empty);
    }
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

        section.querySelector('[data-action="delete"]')!.addEventListener('click', () => openDeleteStorageModal(storage));
        setupItemDropTarget(section, storage.uuid);
        setupStorageDragging(section);

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

    element.addEventListener('dragstart', (event) => {
        const data: DraggedItem = {...item, from};
        event.dataTransfer!.setData(ITEM_MIME, JSON.stringify(data));
        event.dataTransfer!.effectAllowed = 'move';
    });

    return element;
}

function setField(root: HTMLElement, field: string, value: string): void {
    const element = root.querySelector(`[data-field="${field}"]`);
    if (element !== null) {
        element.textContent = value;
    }
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

function toggleSidebar(): void {
    const sidebar = document.getElementById('inventory-sidebar')!;
    sidebar.classList.toggle('collapsed');
    localStorage.setItem('dnd-restaurant-sidebar-collapsed', sidebar.classList.contains('collapsed') ? '1' : '0');
}

function switchTab(tab: SidebarTab): void {
    activeTab = tab;

    document.querySelectorAll('.inventory-tab[data-tab]').forEach((button) => {
        button.classList.toggle('active', (button as HTMLElement).dataset.tab === tab);
    });

    document.getElementById('inventory-create-type')!.textContent = `+ New ${TYPE_LABELS[tab]}`;
    renderSidebar();
}

//| General storage

function addToGeneral(type: ItemType, uuid: string, amount: number): void {
    const key = itemKey(type, uuid);
    const existing = generalStorage.get(key);

    if (existing !== undefined) {
        existing.amount += amount;
    } else {
        generalStorage.set(key, {type, uuid, amount});
    }

    render();
}

function removeFromGeneral(type: ItemType, uuid: string, amount: number): void {
    const key = itemKey(type, uuid);
    const existing = generalStorage.get(key);
    if (existing === undefined) return;

    existing.amount -= amount;
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
        const response = await postData(`${apiUrl}/equipment/${uuid}/owned`, formData);
        if (!response?.ok) return;

        setInventory(response.data.inventory);
    } catch {
        // The error has already been shown to the user
    }
}

//| Drag and drop: items

/** Shows the discard edge zone only while an inventory item is actively being dragged, without displacing layout */
function setupDiscardEdgeVisibility(): void {
    const edge = document.getElementById('discard-edge') as HTMLElement;

    document.addEventListener('dragstart', (event) => {
        if (event.dataTransfer?.types.includes(ITEM_MIME)) {
            edge.classList.add('visible');
        }
    });

    document.addEventListener('dragend', () => {
        edge.classList.remove('visible');
    });
}

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

    if (item.amount <= 1) {
        moveItem(item, 1, target);
        return;
    }

    const name = getDefinition(item.type, item.uuid)?.name ?? 'item';
    const title = target === DISCARD ? `Remove ${name}` : `Move ${name}`;
    openAmountModal(title, item.amount, item.amount, (amount) => moveItem(item, amount, target));
}

async function moveItem(item: DraggedItem, amount: number, target: string): Promise<void> {
    // Unsaved items only need to be saved when they go into a storage space
    if (item.from === null) {
        if (target === DISCARD) {
            removeFromGeneral(item.type, item.uuid, amount);
            return;
        }

        if (await transfer(item, amount, null, target)) {
            removeFromGeneral(item.type, item.uuid, amount);
        }
        return;
    }

    const to = target === GENERAL || target === DISCARD ? null : target;
    if (await transfer(item, amount, item.from, to) && target === GENERAL) {
        addToGeneral(item.type, item.uuid, amount);
    }
}

async function transfer(item: Item, amount: number, from: string | null, to: string | null): Promise<boolean> {
    const formData = new FormData();
    formData.append('type', item.type);
    formData.append('item_uuid', item.uuid);
    formData.append('amount', String(amount));
    if (from !== null) formData.append('from_storage_uuid', from);
    if (to !== null) formData.append('to_storage_uuid', to);

    try {
        const response = await postData(`${apiUrl}/transfer`, formData);
        if (!response?.ok) return false;

        setInventory(response.data.inventory);
        return true;
    } catch {
        // The error has already been shown to the user
        return false;
    }
}

//| Drag and drop: storage reordering

function setupStorageDragging(section: HTMLElement): void {
    // Drags of the items inside the space bubble up here too, so only handle drags of the space itself
    section.addEventListener('dragstart', (event) => {
        if (event.target !== section) return;

        draggedStorage = section;
        event.dataTransfer!.setData(STORAGE_MIME, section.dataset.uuid!);
        event.dataTransfer!.effectAllowed = 'move';
        section.classList.add('dragging');
    });

    section.addEventListener('dragend', (event) => {
        if (event.target !== section) return;

        section.classList.remove('dragging');
        if (draggedStorage !== null) {
            // The drag was cancelled, so restore the saved order
            draggedStorage = null;
            renderStorages();
        }
    });
}

function setupStorageReordering(list: HTMLElement): void {
    list.addEventListener('dragover', (event) => {
        if (draggedStorage === null || !event.dataTransfer!.types.includes(STORAGE_MIME)) return;
        event.preventDefault();

        const target = (event.target as Element).closest('.inventory-storage') as HTMLElement | null;
        if (target === null || target === draggedStorage || target.parentElement !== list) return;

        // The dragged space takes over the slot of the hovered one, shifting it and those in between along. As the
        // dragged space ends up under the pointer afterwards, this doesn't flip back and forth on the next dragover
        const siblings = Array.from(list.children);
        const movingForward = siblings.indexOf(draggedStorage) < siblings.indexOf(target);
        list.insertBefore(draggedStorage, movingForward ? target.nextSibling : target);
    });

    list.addEventListener('drop', async (event) => {
        if (draggedStorage === null) return;
        event.preventDefault();

        draggedStorage.classList.remove('dragging');
        draggedStorage = null;

        const order = Array.from(list.querySelectorAll(':scope > .inventory-storage'))
            .map((section) => (section as HTMLElement).dataset.uuid!);

        if (order.join() === inventory.storages.map((storage) => storage.uuid).join()) {
            return;
        }

        const formData = new FormData();
        order.forEach((uuid) => formData.append('order[]', uuid));

        try {
            const response = await postData(`${apiUrl}/storage/reorder`, formData);
            if (response?.ok) {
                setInventory(response.data.inventory);
                return;
            }
        } catch {
            // The error has already been shown to the user
        }

        renderStorages();
    });
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
        const response = await postData(`${apiUrl}/storage/${uuid}/delete`, new FormData());
        if (!response?.ok) return;
        response.announce();

        setInventory(response.data.inventory);
        for (const item of response.data.released as Item[]) {
            addToGeneral(item.type, item.uuid, item.amount);
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

    if (activeTab === 'ingredient') {
        editingIngredientUuid = null;
        (document.getElementById('ingredient-create-form') as HTMLFormElement).reset();
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
        (form.elements.namedItem('name') as HTMLInputElement).value = ingredient.name;
        (form.elements.namedItem('expires_in') as HTMLInputElement).value = String(ingredient.expires_in);
        (form.elements.namedItem('size') as HTMLInputElement).value = String(ingredient.size);

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
        openAmountModal(`Add ${name} to general storage`, 1, null, (amount) => addToGeneral('ingredient', response.uuid, amount));
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

async function submitForm(form: HTMLFormElement, url: string, fileFields: string[] = []): Promise<any | null> {
    const formData = new FormData(form);

    // Don't send empty file inputs, so optional images stay optional
    for (const field of fileFields) {
        const file = formData.get(field);
        if (file instanceof File && file.size === 0) {
            formData.delete(field);
        }
    }

    try {
        const response = await postData(url, formData);
        if (!response?.ok) return null;

        response.announce();
        return response.data;
    } catch {
        // The error has already been shown to the user
        return null;
    }
}

//| Recipes

function openRecipeModal(uuid: string | null): void {
    const recipe = uuid !== null ? inventory.recipes.find((candidate) => candidate.uuid === uuid) ?? null : null;
    if (uuid !== null && recipe === null) return;

    editingRecipeUuid = uuid;
    const form = document.getElementById('recipe-form') as HTMLFormElement;
    form.reset();

    if (recipe !== null) {
        (form.elements.namedItem('name') as HTMLInputElement).value = recipe.name;
        (form.elements.namedItem('description') as HTMLTextAreaElement).value = recipe.description ?? '';
        for (const field of RECIPE_NUMBER_FIELDS) {
            (form.elements.namedItem(field) as HTMLInputElement).value = String(recipe[field]);
        }
    }

    for (const kind of Object.keys(recipeComponents) as RecipeComponentKind[]) {
        recipeComponents[kind].clear();
        recipe?.[RECIPE_COMPONENT_KEYS[kind]].forEach((component) => recipeComponents[kind].set(component.uuid, component.amount));
        renderRecipeComponents(kind);
    }

    switchRecipeTab('details');
    document.getElementById('recipe-modal-title')!.textContent = recipe !== null ? 'Edit Recipe' : 'New Recipe';
    document.getElementById('recipe-modal-submit')!.textContent = recipe !== null ? 'Save' : 'Create';
    document.getElementById('recipe-modal-delete')!.classList.toggle('hidden', recipe === null);
    openModal('recipe-modal');
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
function getComponentItemType(kind: RecipeComponentKind): ItemType {
    return kind === 'equipment' ? 'equipment' : 'ingredient';
}

function renderRecipeComponents(kind: RecipeComponentKind): void {
    const list = document.getElementById(`recipe-${kind}-list`) as HTMLElement;
    const template = document.getElementById('recipe-component-template') as HTMLTemplateElement;
    const components = recipeComponents[kind];
    const type = getComponentItemType(kind);

    list.replaceChildren();
    for (const [uuid, amount] of components) {
        const definition = getDefinition(type, uuid);
        const row = template.content.firstElementChild!.cloneNode(true) as HTMLElement;
        setIcon(row, definition !== null && 'icon' in definition ? definition.icon : null);
        setField(row, 'name', definition?.name ?? 'Unknown');

        // Invalid amounts are left for the form's own validation to report on save
        const input = row.querySelector('[data-field="amount"]') as HTMLInputElement;
        input.value = String(amount);
        input.addEventListener('input', () => {
            const value = parseInt(input.value, 10);
            if (!isNaN(value)) components.set(uuid, value);
        });

        row.querySelector('[data-action="remove"]')!.addEventListener('click', () => {
            components.delete(uuid);
            renderRecipeComponents(kind);
        });

        list.appendChild(row);
    }

    if (components.size === 0) {
        const empty = document.createElement('li');
        empty.className = 'text-gray-400 text-sm px-3 py-1';
        empty.textContent = 'None yet';
        list.appendChild(empty);
    }

    document.querySelector(`[data-recipe-count="${kind}"]`)!.textContent = components.size > 0 ? `(${components.size})` : '';

    // Only offer the types that are not part of the list yet
    const select = document.getElementById(`recipe-${kind}-select`) as HTMLSelectElement;
    const types: (IngredientType | EquipmentType)[] = type === 'ingredient' ? inventory.ingredients : inventory.equipment;
    select.replaceChildren();
    for (const definition of types) {
        if (!components.has(definition.uuid)) {
            select.appendChild(new Option(definition.name, definition.uuid));
        }
    }

    select.disabled = select.options.length === 0;
    if (select.disabled) {
        select.appendChild(new Option(`No ${TYPE_LABELS[type].toLowerCase()} left to add`, ''));
    }
}

function addRecipeComponent(kind: RecipeComponentKind): void {
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
    for (const kind of Object.keys(recipeComponents) as RecipeComponentKind[]) {
        payload[RECIPE_COMPONENT_KEYS[kind]] = Array.from(recipeComponents[kind], ([uuid, amount]): RecipeComponent => ({uuid, amount}));
    }

    try {
        const response = await postData(url, JSON.stringify(payload));
        if (!response?.ok) return;
        response.announce();

        editingRecipeUuid = null;
        closeModal('recipe-modal');
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
    }
}

function openDeleteTypeModal(type: SidebarTab): void {
    const uuid = getEditingUuid(type);
    if (uuid === null) return;

    const name = type === 'recipe'
        ? inventory.recipes.find((recipe) => recipe.uuid === uuid)?.name
        : getDefinition(type, uuid)?.name;

    pendingTypeDelete = {type, uuid};
    document.getElementById('type-delete-title')!.textContent = `Delete ${TYPE_LABELS[type].toLowerCase()}`;
    document.getElementById('type-delete-name')!.textContent = name ?? TYPE_LABELS[type];
    document.getElementById('type-delete-warning')!.textContent = type === 'recipe'
        ? ''
        : 'It will also be removed from every storage space and recipe.';

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
        const response = await postData(`${apiUrl}/${type}/${uuid}/delete`, new FormData());
        if (!response?.ok) return;
        response.announce();

        if (type === 'ingredient') editingIngredientUuid = null;
        if (type === 'equipment') editingEquipmentUuid = null;
        if (type === 'recipe') editingRecipeUuid = null;

        // Also drops any unsaved general storage stacks of a deleted ingredient or equipment
        setInventory(response.data.inventory);
    } catch {
        // The error has already been shown to the user
    }
}

//| Amount modal

function openAmountModal(title: string, amount: number, max: number | null, action: (amount: number) => Promise<void> | void): void {
    pendingAmountAction = action;
    pendingAmountMax = max;

    document.getElementById('amount-modal-title')!.textContent = title;
    const input = document.getElementById('amount-modal-input') as HTMLInputElement;
    input.value = String(amount);
    input.max = max !== null ? String(max) : '';

    openModal('amount-modal');
    input.focus();
    input.select();
}

async function confirmAmount(): Promise<void> {
    const amount = parseInt((document.getElementById('amount-modal-input') as HTMLInputElement).value, 10);
    if (isNaN(amount) || amount < 1 || (pendingAmountMax !== null && amount > pendingAmountMax)) {
        toast(pendingAmountMax !== null ? `Enter an amount between 1 and ${pendingAmountMax}` : 'Enter an amount of at least 1');
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

function itemKey(type: ItemType, uuid: string): string {
    return `${type}:${uuid}`;
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
(<any>window).addRecipeComponent     = addRecipeComponent;
(<any>window).switchRecipeTab        = switchRecipeTab;
(<any>window).openDeleteTypeModal    = openDeleteTypeModal;
(<any>window).cancelDeleteType       = cancelDeleteType;
(<any>window).deleteType             = deleteType;
(<any>window).confirmAmount          = confirmAmount;
(<any>window).openModal              = openModal;
(<any>window).closeModal             = closeModal;
