import {closeModal, openModal} from "../../../../components/modal";
import {createReorderableList, setField, setupDropTarget, showWhileDragging, submitForm} from "../shared";
import {cloneTemplate, setIcon} from "../dom";
import {openAmountModal} from "./amount-modal";
import {
    describe, generalStorage, getDefinition, getIcon, itemKey, postInventory, render, roundAmount, setInventory, state,
} from "./inventory";
import {DISCARD, DraggedItem, GENERAL, IngredientType, Item, ITEM_MIME, ItemType, PILE, STORAGE_MIME, StorageSpace} from "./types";

/** The storage spaces, the pile of ingredients brought in during service and the unsaved general storage, and
 * dragging items between them */

/** The inventory item being dragged, as drop targets can't read drag data until the drop itself */
let draggedItem: DraggedItem | null = null;

let pendingDeleteUuid: string | null = null;

/** Makes a rendered storage space reorderable by dragging */
let registerStorageCard: (card: HTMLElement) => void = () => {};

/** Sets up dropping items into general storage and the discard zone, and reordering the storage spaces. Players
 * neither add items, take them out, nor arrange the storage spaces, so this is left out for them */
export function setupStorageArea(): void {
    const general = document.getElementById('general-storage') as HTMLElement;
    setupItemDropTarget(general, GENERAL);
    // An empty general storage is hidden, though it still shows while an item is dragged so it can be dropped there
    showWhileDragging(general, ITEM_MIME);

    const discard = document.getElementById('discard-edge') as HTMLElement;
    setupItemDropTarget(discard, DISCARD);
    showWhileDragging(discard, ITEM_MIME);

    registerStorageCard = createReorderableList(document.getElementById('storage-list') as HTMLElement, {
        mime: STORAGE_MIME,
        getSavedOrder: () => state.inventory.storages.map((storage) => storage.uuid),
        save: saveStorageOrder,
        restore: renderStorages,
    });
}

/** The inventory item being dragged, if any */
export function getDraggedItem(): DraggedItem | null {
    return draggedItem;
}

//| Rendering

/** Shows the ingredients brought in during service, which are moved out of the pile like out of a storage space */
export function renderPile(): void {
    const section = document.getElementById('pile-storage') as HTMLElement;
    const container = section.querySelector('[data-items]') as HTMLElement;
    container.replaceChildren(...state.inventory.pile.map((item) => createItemElement(item, PILE)));

    section.classList.toggle('hidden', state.inventory.pile.length === 0);
}

export function renderGeneralStorage(): void {
    const container = document.querySelector('#general-storage [data-items]') as HTMLElement;
    container.replaceChildren(...sortItems([...generalStorage.values()]).map((item) => createItemElement(item, null)));

    document.getElementById('general-storage')!.classList.toggle('empty', generalStorage.size === 0);
}

export function renderStorages(): void {
    const list = document.getElementById('storage-list') as HTMLElement;

    list.replaceChildren();
    for (const storage of state.inventory.storages) {
        const section = cloneTemplate('storage-template');
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
        items.append(...storage.items.map((item) => createItemElement(item, storage.uuid)));

        if (state.isPlayer) {
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

    document.getElementById('storage-empty')!.classList.toggle('hidden', state.inventory.storages.length > 0);
}

function createItemElement(item: Item, from: string | null): HTMLElement {
    const element = cloneTemplate('item-template');
    const definition = getDefinition(item.type, item.uuid);

    element.dataset.type = item.type;
    element.title = describe(item.type, item.uuid);
    setIcon(element, getIcon(definition));
    setField(element, 'name', definition?.name ?? 'Unknown');
    setField(element, 'amount', `×${item.amount}`);
    setField(element, 'expiry', describeDaysLeft(item));
    element.classList.toggle('expiring', item.days_left !== undefined && item.days_left !== null && item.days_left <= 1);

    // Players only move stored ingredients
    if (state.isPlayer && (item.type !== 'ingredient' || from === null)) {
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

//| General storage

/**
 * Adds an item to the unsaved general storage, on the stack with the same days left
 * @param daysLeft The days an ingredient has left, or null when it is fresh or doesn't expire
 */
export function addToGeneral(type: ItemType, uuid: string, amount: number, daysLeft: number | null = null): void {
    const key = itemKey(type, uuid, daysLeft);
    const existing = generalStorage.get(key);

    if (existing !== undefined) {
        existing.amount = roundAmount(existing.amount + amount);
    } else {
        generalStorage.set(key, {type, uuid, amount, days_left: daysLeft});
    }

    render();
}

export function removeFromGeneral(type: ItemType, uuid: string, amount: number, daysLeft: number | null = null): void {
    const key = itemKey(type, uuid, daysLeft);
    const existing = generalStorage.get(key);
    if (existing === undefined) return;

    existing.amount = roundAmount(existing.amount - amount);
    if (existing.amount <= 0) {
        generalStorage.delete(key);
    }

    render();
}

//| Drag and drop: items

/** Makes an element accept dropped inventory items, moving them to the given target */
function setupItemDropTarget(element: HTMLElement, target: string): void {
    setupDropTarget(element, ITEM_MIME, (data) => handleItemDrop(JSON.parse(data) as DraggedItem, target));
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
    const daysLeft = item.days_left ?? null;

    // Unsaved items only need to be saved when they go into a storage space
    if (item.from === null) {
        if (target === DISCARD || await transfer(item, amount, null, target)) {
            removeFromGeneral(item.type, item.uuid, amount, daysLeft);
        }
        return;
    }

    const to = target === GENERAL || target === DISCARD ? null : target;
    if (await transfer(item, amount, item.from, to) && target === GENERAL) {
        addToGeneral(item.type, item.uuid, amount, daysLeft);
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

    return await postInventory(`${state.apiUrl}/transfer`, formData) !== null;
}

//| Storage spaces

async function saveStorageOrder(order: string[]): Promise<void> {
    const formData = new FormData();
    order.forEach((uuid) => formData.append('order[]', uuid));

    if (await postInventory(`${state.apiUrl}/storage/reorder`, formData) === null) {
        renderStorages();
    }
}

export function openCreateStorageModal(): void {
    (document.getElementById('storage-create-form') as HTMLFormElement).reset();
    openModal('storage-create-modal');
}

export async function createStorage(): Promise<void> {
    const form = document.getElementById('storage-create-form') as HTMLFormElement;
    if (!form.reportValidity()) return;

    const response = await submitForm(form, `${state.apiUrl}/storage/create`, ['background_image']);
    if (response === null) return;

    closeModal('storage-create-modal');
    setInventory(response.inventory);
}

function openDeleteStorageModal(storage: StorageSpace): void {
    pendingDeleteUuid = storage.uuid;
    document.getElementById('storage-delete-name')!.textContent = storage.name;
    openModal('storage-delete-modal');
}

export async function deleteStorage(): Promise<void> {
    if (pendingDeleteUuid === null) return;

    const uuid = pendingDeleteUuid;
    pendingDeleteUuid = null;
    closeModal('storage-delete-modal');

    // What was stored in the space is released into general storage
    const data = await postInventory(`${state.apiUrl}/storage/${uuid}/delete`, new FormData(), true);
    for (const item of (data?.released ?? []) as Item[]) {
        addToGeneral(item.type, item.uuid, item.amount, item.days_left ?? null);
    }
}
