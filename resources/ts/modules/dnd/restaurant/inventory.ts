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
    category: string;
    size: number;
    cleaning_time: number;
    multiplier: number;
    parent_uuid: string | null;
    requires_storage: boolean;
    stored: number;
}

interface Inventory {
    storages: StorageSpace[];
    ingredients: IngredientType[];
    equipment: EquipmentType[];
}

interface DraggedItem extends Item {
    from: string | null;
}

/** Drag data types, so item drags and storage reorder drags can be told apart during dragover */
const ITEM_MIME = 'application/x-inventory-item';
const STORAGE_MIME = 'application/x-inventory-storage';

/** Drop target keys for the unsaved general storage and the discard zone */
const GENERAL = 'general';
const DISCARD = 'discard';

const TYPE_LABELS: Record<ItemType, string> = {
    ingredient: 'Ingredient',
    equipment:  'Equipment',
};

let apiUrl = '';
let inventory: Inventory = {storages: [], ingredients: [], equipment: []};
let activeTab: ItemType = 'ingredient';

/** The unsaved general storage, keyed by "type:uuid". Only exists client side */
const generalStorage = new Map<string, Item>();

let pendingAmountAction: ((amount: number) => Promise<void> | void) | null = null;
let pendingAmountMax: number | null = null;
let pendingDeleteUuid: string | null = null;
let draggedStorage: HTMLElement | null = null;

/** The uuid of the ingredient or equipment currently being edited, or null when the create modal is for a new type */
let editingIngredientUuid: string | null = null;
let editingEquipmentUuid: string | null = null;

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
    const template = document.getElementById('list-row-template') as HTMLTemplateElement;

    const types: (IngredientType | EquipmentType)[] = activeTab === 'ingredient' ? inventory.ingredients : inventory.equipment;
    const matches = types.filter((type) => {
        const haystack = 'category' in type ? `${type.name} ${type.category}` : type.name;
        return haystack.toLowerCase().includes(search);
    });

    list.replaceChildren();
    for (const type of matches) {
        const row = template.content.firstElementChild!.cloneNode(true) as HTMLElement;
        setIcon(row, 'icon' in type ? type.icon : null);
        setField(row, 'name', type.name);

        const unsaved = generalStorage.get(itemKey(activeTab, type.uuid))?.amount ?? 0;
        setField(row, 'stored', unsaved > 0 ? `${type.stored} (+${unsaved})` : `${type.stored}`);
        row.title = describe(activeTab, type.uuid);

        const itemType = activeTab;
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

    if (matches.length === 0) {
        const empty = document.createElement('li');
        empty.className = 'text-center text-gray-400 py-4 text-sm';
        empty.textContent = types.length === 0 ? `No ${TYPE_LABELS[activeTab].toLowerCase()} yet` : 'No results';
        list.appendChild(empty);
    }
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
        setupStorageHandle(section);

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

function switchTab(tab: ItemType): void {
    activeTab = tab;

    document.querySelectorAll('.inventory-tab').forEach((button) => {
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

function setupStorageHandle(section: HTMLElement): void {
    const handle = section.querySelector('.inventory-storage-handle') as HTMLElement;

    handle.addEventListener('dragstart', (event) => {
        draggedStorage = section;
        event.dataTransfer!.setData(STORAGE_MIME, section.dataset.uuid!);
        event.dataTransfer!.effectAllowed = 'move';
        event.dataTransfer!.setDragImage(section, 20, 20);
        section.classList.add('dragging');
    });

    handle.addEventListener('dragend', () => {
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

        const rect = target.getBoundingClientRect();
        const before = event.clientX < rect.left + rect.width / 2;
        list.insertBefore(draggedStorage, before ? target : target.nextSibling);
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
    if (activeTab === 'ingredient') {
        editingIngredientUuid = null;
        (document.getElementById('ingredient-create-form') as HTMLFormElement).reset();
        document.getElementById('ingredient-modal-title')!.textContent = 'New Ingredient';
        document.getElementById('ingredient-modal-submit')!.textContent = 'Create';
        openModal('ingredient-create-modal');
        return;
    }

    editingEquipmentUuid = null;
    (document.getElementById('equipment-create-form') as HTMLFormElement).reset();
    fillEquipmentParentSelect(null);
    document.getElementById('equipment-modal-title')!.textContent = 'New Equipment';
    document.getElementById('equipment-modal-submit')!.textContent = 'Create';
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
        openModal('ingredient-create-modal');
        return;
    }

    const equipment = inventory.equipment.find((candidate) => candidate.uuid === uuid);
    if (equipment === undefined) return;

    editingEquipmentUuid = uuid;
    const form = document.getElementById('equipment-create-form') as HTMLFormElement;
    form.reset();
    (form.elements.namedItem('name') as HTMLInputElement).value = equipment.name;
    (form.elements.namedItem('category') as HTMLInputElement).value = equipment.category;
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

    return `${definition.name} (${definition.category}) — size ${definition.size}`;
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
(<any>window).confirmAmount          = confirmAmount;
(<any>window).openModal              = openModal;
(<any>window).closeModal             = closeModal;
