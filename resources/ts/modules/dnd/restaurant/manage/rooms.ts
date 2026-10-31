import {closeModal, openModal} from "../../../../components/modal";
import {createReorderableList, setField, setupDropTarget, showWhileDragging, submitForm} from "../shared";
import {cloneTemplate} from "../dom";
import {postFloor, Room, seatsLabel, setFloor, state, Table} from "./floor";

/** The rooms of the restaurant, and placing tables in them by dragging */

/** What is dragged when a table is moved between rooms */
interface DraggedTable {
    uuid: string;
    from: string | null;
}

/** Drag data types, so table drags and room reorder drags can be told apart during dragover */
const TABLE_MIME = 'application/x-restaurant-table';
const ROOM_MIME = 'application/x-restaurant-room';

let pendingRoomDelete: string | null = null;

/** Makes a rendered room reorderable by dragging */
let registerRoomCard: (card: HTMLElement) => void = () => {};

/** Sets up taking tables out of their room by dropping them on the discard edge, and reordering the rooms */
export function setupRoomArea(): void {
    const edge = document.getElementById('discard-edge') as HTMLElement;
    setupTableDropTarget(edge, null);
    showWhileDragging(edge, TABLE_MIME);

    registerRoomCard = createReorderableList(document.getElementById('room-list') as HTMLElement, {
        mime: ROOM_MIME,
        getSavedOrder: () => state.floor.rooms.map((room) => room.uuid),
        save: saveRoomOrder,
        restore: renderRooms,
    });
}

//| Rendering

export function renderRooms(): void {
    const list = document.getElementById('room-list') as HTMLElement;

    list.replaceChildren();
    for (const room of state.floor.rooms) {
        const section = cloneTemplate('room-template');
        section.dataset.uuid = room.uuid;

        if (room.background_image !== null) {
            const background = section.querySelector('.inventory-storage-background') as HTMLElement;
            background.style.backgroundImage = `url(${JSON.stringify(room.background_image)})`;
        }

        const tables = state.floor.tables.filter((table) => table.room_uuid === room.uuid);
        setField(section, 'name', room.name);
        setField(section, 'seats', seatsLabel(tables.reduce((total, table) => total + table.capacity, 0)));

        const items = section.querySelector('[data-items]') as HTMLElement;
        items.append(...tables.map(createTableChip));

        section.querySelector('[data-action="delete"]')!.addEventListener('click', () => openDeleteRoomModal(room));
        setupTableDropTarget(section, room.uuid);
        registerRoomCard(section);

        list.appendChild(section);
    }

    document.getElementById('room-empty')!.classList.toggle('hidden', state.floor.rooms.length > 0);
}

function createTableChip(table: Table): HTMLElement {
    const chip = cloneTemplate('table-chip-template');

    setField(chip, 'name', table.name);
    setField(chip, 'capacity', seatsLabel(table.capacity));
    chip.title = `${table.name} — ${seatsLabel(table.capacity)}, ${table.seated} seated`;
    setupTableDrag(chip, table);

    return chip;
}

//| Drag and drop: tables

export function setupTableDrag(element: HTMLElement, table: Table): void {
    element.addEventListener('dragstart', (event) => {
        const data: DraggedTable = {uuid: table.uuid, from: table.room_uuid};
        event.dataTransfer!.setData(TABLE_MIME, JSON.stringify(data));
        event.dataTransfer!.effectAllowed = 'move';
    });
}

/**
 * Makes an element accept dropped tables
 * @param element The element to drop on
 * @param roomUuid The room dropped tables are placed in, or null to take them out of their room
 */
function setupTableDropTarget(element: HTMLElement, roomUuid: string | null): void {
    setupDropTarget(element, TABLE_MIME, (data) => {
        const table = JSON.parse(data) as DraggedTable;
        if (table.from !== roomUuid) {
            moveTable(table.uuid, roomUuid);
        }
    });
}

async function moveTable(uuid: string, roomUuid: string | null): Promise<void> {
    const formData = new FormData();
    if (roomUuid !== null) formData.append('room_uuid', roomUuid);

    await postFloor(`${state.apiUrl}/table/${uuid}/move`, formData);
}

//| Rooms

export function openCreateRoomModal(): void {
    (document.getElementById('room-create-form') as HTMLFormElement).reset();
    openModal('room-create-modal');
}

export async function createRoom(): Promise<void> {
    const form = document.getElementById('room-create-form') as HTMLFormElement;
    if (!form.reportValidity()) return;

    const response = await submitForm(form, `${state.apiUrl}/room/create`, ['background_image']);
    if (response === null) return;

    closeModal('room-create-modal');
    setFloor(response.floor);
}

async function saveRoomOrder(order: string[]): Promise<void> {
    const formData = new FormData();
    order.forEach((uuid) => formData.append('order[]', uuid));

    if (!await postFloor(`${state.apiUrl}/room/reorder`, formData, false)) {
        renderRooms();
    }
}

function openDeleteRoomModal(room: Room): void {
    pendingRoomDelete = room.uuid;
    document.getElementById('room-delete-name')!.textContent = room.name;
    openModal('room-delete-modal');
}

export async function deleteRoom(): Promise<void> {
    if (pendingRoomDelete === null) return;

    const uuid = pendingRoomDelete;
    pendingRoomDelete = null;
    closeModal('room-delete-modal');

    await postFloor(`${state.apiUrl}/room/${uuid}/delete`);
}
