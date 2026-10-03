import {getData} from "../../../main";
import {openModal, init as initModals, closeModal} from "../../../components/modal";
import {createReorderableList, postOnce, restoreSidebar, setField, showWhileDragging, submitForm, toggleSidebar} from "./shared";

interface Room {
    uuid: string;
    name: string;
    background_image: string | null;
}

interface Table {
    uuid: string;
    name: string;
    capacity: number;
    /** Null while the table is not placed in a room */
    room_uuid: string | null;
    /** How many customers are seated at the table */
    seated: number;
}

interface Customer {
    uuid: string;
    name: string;
    description: string | null;
    /** Both null while the customer is not seated */
    table_uuid: string | null;
    seat: number | null;
}

interface Employee {
    uuid: string;
    name: string;
    icon: string | null;
    cleaning_speed: number;
    prep_speed: number;
}

interface Floor {
    rooms: Room[];
    tables: Table[];
    customers: Customer[];
    employees: Employee[];
}

/** The sidebar lists tables, customers and employees */
type SidebarTab = 'table' | 'customer' | 'employee';

/** What is dragged when a table is moved between rooms */
interface DraggedTable {
    uuid: string;
    from: string | null;
}

/** Drag data types, so table drags and room reorder drags can be told apart during dragover */
const TABLE_MIME = 'application/x-restaurant-table';
const ROOM_MIME = 'application/x-restaurant-room';

const TAB_LABELS: Record<SidebarTab, string> = {
    table:    'Table',
    customer: 'Customer',
    employee: 'Employee',
};

let apiUrl = '';
let editUrl = '';
let floor: Floor = {rooms: [], tables: [], customers: [], employees: []};
let activeTab: SidebarTab = 'table';

let editingTableUuid: string | null = null;
let editingCustomerUuid: string | null = null;
let editingEmployeeUuid: string | null = null;
let pendingRoomDelete: string | null = null;
let pendingDelete: {type: SidebarTab, uuid: string} | null = null;

/** Makes a rendered room reorderable by dragging */
let registerRoomCard: (card: HTMLElement) => void = () => {};

//| Initialisation

async function init(): Promise<void> {
    const root = document.getElementById('manage') as HTMLElement;
    apiUrl = root.dataset.apiUrl!;
    editUrl = root.dataset.editUrl!;

    initModals();
    restoreSidebar();

    const edge = document.getElementById('discard-edge') as HTMLElement;
    setupTableDropTarget(edge, null);
    showWhileDragging(edge, TABLE_MIME);

    registerRoomCard = createReorderableList(document.getElementById('room-list') as HTMLElement, {
        mime: ROOM_MIME,
        getSavedOrder: () => floor.rooms.map((room) => room.uuid),
        save: saveRoomOrder,
        restore: renderRooms,
    });

    const response = await getData(apiUrl);
    setFloor(response.data);
}

function setFloor(data: Floor): void {
    floor = data;
    renderSidebar();
    renderRooms();
}

//| Rendering

function renderSidebar(): void {
    const list = document.getElementById('inventory-list') as HTMLElement;
    const search = (document.getElementById('inventory-search') as HTMLInputElement).value.trim().toLowerCase();

    list.replaceChildren();
    const total = activeTab === 'table'
        ? renderTableRows(list, search)
        : activeTab === 'customer' ? renderCustomerRows(list, search) : renderEmployeeRows(list, search);

    if (list.childElementCount === 0) {
        const empty = document.createElement('li');
        empty.className = 'text-center text-gray-400 py-4 text-sm';
        empty.textContent = total === 0 ? `No ${TAB_LABELS[activeTab].toLowerCase()}s yet` : 'No results';
        list.appendChild(empty);
    }
}

function createListRow(): HTMLElement {
    const template = document.getElementById('list-row-template') as HTMLTemplateElement;
    return template.content.firstElementChild!.cloneNode(true) as HTMLElement;
}

/** Renders the sidebar rows for tables, returning how many tables exist before filtering */
function renderTableRows(list: HTMLElement, search: string): number {
    for (const table of floor.tables.filter((table) => table.name.toLowerCase().includes(search))) {
        const row = createListRow();
        setField(row, 'name', table.name);
        setField(row, 'meta', `${seatsLabel(table.capacity)} · ${getRoom(table.room_uuid)?.name ?? 'Not placed'}`);
        row.querySelector('[data-action="edit"]')!.addEventListener('click', () => openTableModal(table.uuid));

        // Tables are placed in rooms by dragging them from the sidebar
        row.draggable = true;
        setupTableDrag(row, table);

        list.appendChild(row);
    }

    return floor.tables.length;
}

/** Renders the sidebar rows for customers, returning how many customers exist before filtering */
function renderCustomerRows(list: HTMLElement, search: string): number {
    for (const customer of floor.customers.filter((customer) => customer.name.toLowerCase().includes(search))) {
        const row = createListRow();
        setField(row, 'name', customer.name);
        setField(row, 'meta', customer.table_uuid !== null
            ? `Seated at ${getTable(customer.table_uuid)?.name ?? 'a table'}, seat ${customer.seat}`
            : 'Not seated');
        row.title = customer.description ?? '';
        row.querySelector('[data-action="edit"]')!.addEventListener('click', () => openCustomerModal(customer.uuid));

        list.appendChild(row);
    }

    return floor.customers.length;
}

/** Renders the sidebar rows for employees, returning how many employees exist before filtering */
function renderEmployeeRows(list: HTMLElement, search: string): number {
    for (const employee of floor.employees.filter((employee) => employee.name.toLowerCase().includes(search))) {
        const row = createListRow();
        setField(row, 'name', employee.name);
        setField(row, 'meta', `Prep ×${employee.prep_speed} · Cleaning ×${employee.cleaning_speed}`);
        row.querySelector('[data-action="edit"]')!.addEventListener('click', () => openEmployeeModal(employee.uuid));

        list.appendChild(row);
    }

    return floor.employees.length;
}

function renderRooms(): void {
    const list = document.getElementById('room-list') as HTMLElement;
    const template = document.getElementById('room-template') as HTMLTemplateElement;

    list.replaceChildren();
    for (const room of floor.rooms) {
        const section = template.content.firstElementChild!.cloneNode(true) as HTMLElement;
        section.dataset.uuid = room.uuid;

        if (room.background_image !== null) {
            const background = section.querySelector('.inventory-storage-background') as HTMLElement;
            background.style.backgroundImage = `url(${JSON.stringify(room.background_image)})`;
        }

        const tables = floor.tables.filter((table) => table.room_uuid === room.uuid);
        setField(section, 'name', room.name);
        setField(section, 'seats', seatsLabel(tables.reduce((total, table) => total + table.capacity, 0)));

        const items = section.querySelector('[data-items]') as HTMLElement;
        for (const table of tables) {
            items.appendChild(createTableChip(table));
        }

        section.querySelector('[data-action="delete"]')!.addEventListener('click', () => openDeleteRoomModal(room));
        setupTableDropTarget(section, room.uuid);
        registerRoomCard(section);

        list.appendChild(section);
    }

    document.getElementById('room-empty')!.classList.toggle('hidden', floor.rooms.length > 0);
}

function createTableChip(table: Table): HTMLElement {
    const template = document.getElementById('table-chip-template') as HTMLTemplateElement;
    const chip = template.content.firstElementChild!.cloneNode(true) as HTMLElement;

    setField(chip, 'name', table.name);
    setField(chip, 'capacity', seatsLabel(table.capacity));
    chip.title = `${table.name} — ${seatsLabel(table.capacity)}, ${table.seated} seated`;
    setupTableDrag(chip, table);

    return chip;
}

//| Sidebar

function switchTab(tab: SidebarTab): void {
    activeTab = tab;

    document.querySelectorAll<HTMLElement>('.inventory-tab[data-tab]').forEach((button) => {
        button.classList.toggle('active', button.dataset.tab === tab);
    });

    document.getElementById('inventory-create-type')!.textContent = `+ New ${TAB_LABELS[tab]}`;
    renderSidebar();
}

function openCreateModal(): void {
    if (activeTab === 'table') {
        openTableModal(null);
    } else if (activeTab === 'customer') {
        openCustomerModal(null);
    } else {
        openEmployeeModal(null);
    }
}

//| Restaurant details

async function saveRestaurant(): Promise<void> {
    const form = document.getElementById('restaurant-form') as HTMLFormElement;
    if (!form.reportValidity()) return;

    const details = await submitForm(form, editUrl);
    if (details === null) return;

    document.getElementById('restaurant-title-name')!.textContent = details.name;
}

//| Drag and drop: tables

function setupTableDrag(element: HTMLElement, table: Table): void {
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
    element.addEventListener('dragover', (event) => {
        if (!event.dataTransfer!.types.includes(TABLE_MIME)) return;

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
        const raw = event.dataTransfer!.getData(TABLE_MIME);
        element.classList.remove('drop-hover');
        if (raw === '') return;

        event.preventDefault();
        event.stopPropagation();

        const table = JSON.parse(raw) as DraggedTable;
        if (table.from !== roomUuid) {
            moveTable(table.uuid, roomUuid);
        }
    });
}

async function moveTable(uuid: string, roomUuid: string | null): Promise<void> {
    const formData = new FormData();
    if (roomUuid !== null) formData.append('room_uuid', roomUuid);

    await postFloor(`${apiUrl}/table/${uuid}/move`, formData);
}

//| Rooms

function openCreateRoomModal(): void {
    (document.getElementById('room-create-form') as HTMLFormElement).reset();
    openModal('room-create-modal');
}

async function createRoom(): Promise<void> {
    const form = document.getElementById('room-create-form') as HTMLFormElement;
    if (!form.reportValidity()) return;

    const response = await submitForm(form, `${apiUrl}/room/create`, ['background_image']);
    if (response === null) return;

    closeModal('room-create-modal');
    setFloor(response.floor);
}

async function saveRoomOrder(order: string[]): Promise<void> {
    const formData = new FormData();
    order.forEach((uuid) => formData.append('order[]', uuid));

    if (!await postFloor(`${apiUrl}/room/reorder`, formData, false)) {
        renderRooms();
    }
}

function openDeleteRoomModal(room: Room): void {
    pendingRoomDelete = room.uuid;
    document.getElementById('room-delete-name')!.textContent = room.name;
    openModal('room-delete-modal');
}

async function deleteRoom(): Promise<void> {
    if (pendingRoomDelete === null) return;

    const uuid = pendingRoomDelete;
    pendingRoomDelete = null;
    closeModal('room-delete-modal');

    await postFloor(`${apiUrl}/room/${uuid}/delete`);
}

//| Tables

function openTableModal(uuid: string | null): void {
    const table = uuid !== null ? getTable(uuid) : null;
    if (uuid !== null && table === null) return;

    editingTableUuid = uuid;
    const form = document.getElementById('table-form') as HTMLFormElement;
    form.reset();

    if (table !== null) {
        (form.elements.namedItem('name') as HTMLInputElement).value = table.name;
        (form.elements.namedItem('capacity') as HTMLInputElement).value = String(table.capacity);
    }

    document.getElementById('table-modal-title')!.textContent = table !== null ? 'Edit Table' : 'New Table';
    document.getElementById('table-modal-submit')!.textContent = table !== null ? 'Save' : 'Create';
    document.getElementById('table-modal-delete')!.classList.toggle('hidden', table === null);
    openModal('table-modal');
}

async function saveTable(): Promise<void> {
    const form = document.getElementById('table-form') as HTMLFormElement;
    if (!form.reportValidity()) return;

    const editing = editingTableUuid;
    const url = editing !== null ? `${apiUrl}/table/${editing}/edit` : `${apiUrl}/table/create`;

    const response = await submitForm(form, url);
    if (response === null) return;

    editingTableUuid = null;
    closeModal('table-modal');
    setFloor(response.floor);
}

//| Customers

function openCustomerModal(uuid: string | null): void {
    const customer = uuid !== null ? floor.customers.find((candidate) => candidate.uuid === uuid) ?? null : null;
    if (uuid !== null && customer === null) return;

    editingCustomerUuid = uuid;
    const form = document.getElementById('customer-form') as HTMLFormElement;
    form.reset();

    if (customer !== null) {
        (form.elements.namedItem('name') as HTMLInputElement).value = customer.name;
        (form.elements.namedItem('description') as HTMLTextAreaElement).value = customer.description ?? '';
    }

    document.getElementById('customer-modal-title')!.textContent = customer !== null ? 'Edit Customer' : 'New Customer';
    document.getElementById('customer-modal-submit')!.textContent = customer !== null ? 'Save' : 'Create';
    document.getElementById('customer-modal-delete')!.classList.toggle('hidden', customer === null);
    openModal('customer-modal');
}

async function saveCustomer(): Promise<void> {
    const form = document.getElementById('customer-form') as HTMLFormElement;
    if (!form.reportValidity()) return;

    const editing = editingCustomerUuid;
    const url = editing !== null ? `${apiUrl}/customer/${editing}/edit` : `${apiUrl}/customer/create`;

    const response = await submitForm(form, url);
    if (response === null) return;

    editingCustomerUuid = null;
    closeModal('customer-modal');
    setFloor(response.floor);
}

//| Employees

function openEmployeeModal(uuid: string | null): void {
    const employee = uuid !== null ? floor.employees.find((candidate) => candidate.uuid === uuid) ?? null : null;
    if (uuid !== null && employee === null) return;

    editingEmployeeUuid = uuid;
    const form = document.getElementById('employee-form') as HTMLFormElement;
    form.reset();

    if (employee !== null) {
        (form.elements.namedItem('name') as HTMLInputElement).value = employee.name;
        (form.elements.namedItem('prep_speed') as HTMLInputElement).value = String(employee.prep_speed);
        (form.elements.namedItem('cleaning_speed') as HTMLInputElement).value = String(employee.cleaning_speed);
    }

    document.getElementById('employee-modal-title')!.textContent = employee !== null ? 'Edit Employee' : 'New Employee';
    document.getElementById('employee-modal-submit')!.textContent = employee !== null ? 'Save' : 'Create';
    document.getElementById('employee-modal-delete')!.classList.toggle('hidden', employee === null);
    openModal('employee-modal');
}

async function saveEmployee(): Promise<void> {
    const form = document.getElementById('employee-form') as HTMLFormElement;
    if (!form.reportValidity()) return;

    const editing = editingEmployeeUuid;
    const url = editing !== null ? `${apiUrl}/employee/${editing}/edit` : `${apiUrl}/employee/create`;

    // An empty icon field keeps the current icon
    const response = await submitForm(form, url, ['icon']);
    if (response === null) return;

    editingEmployeeUuid = null;
    closeModal('employee-modal');
    setFloor(response.floor);
}

//| Deleting tables, customers & employees

/** The uuid of what is open in each edit modal */
function getEditingUuid(type: SidebarTab): string | null {
    return {table: editingTableUuid, customer: editingCustomerUuid, employee: editingEmployeeUuid}[type];
}

function openDeleteModal(type: SidebarTab): void {
    const uuid = getEditingUuid(type);
    if (uuid === null) return;

    const name = {
        table: () => getTable(uuid)?.name,
        customer: () => floor.customers.find((customer) => customer.uuid === uuid)?.name,
        employee: () => floor.employees.find((employee) => employee.uuid === uuid)?.name,
    }[type]();

    pendingDelete = {type, uuid};
    document.getElementById('type-delete-title')!.textContent = `Delete ${TAB_LABELS[type].toLowerCase()}`;
    document.getElementById('type-delete-name')!.textContent = name ?? TAB_LABELS[type];

    // The edit modal stays filled in, so cancelling can return to it as it was
    closeModal(`${type}-modal`);
    openModal('type-delete-modal');
}

function cancelDelete(): void {
    closeModal('type-delete-modal');
    if (pendingDelete !== null) {
        openModal(`${pendingDelete.type}-modal`);
        pendingDelete = null;
    }
}

async function confirmDelete(): Promise<void> {
    if (pendingDelete === null) return;

    const {type, uuid} = pendingDelete;
    pendingDelete = null;
    closeModal('type-delete-modal');

    if (await postFloor(`${apiUrl}/${type}/${uuid}/delete`)) {
        if (type === 'table') editingTableUuid = null;
        if (type === 'customer') editingCustomerUuid = null;
        if (type === 'employee') editingEmployeeUuid = null;
    }
}

//| Helpers

/**
 * Posts to a floor endpoint and shows the updated floor it returns
 * @return Whether the request succeeded
 */
async function postFloor(url: string, formData: FormData = new FormData(), announce: boolean = true): Promise<boolean> {
    try {
        const response = await postOnce(url, formData);
        if (!response?.ok) return false;
        if (announce) response.announce();

        setFloor(response.data.floor);
        return true;
    } catch {
        // The error has already been shown to the user
        return false;
    }
}

function getRoom(uuid: string | null): Room | null {
    return uuid !== null ? floor.rooms.find((room) => room.uuid === uuid) ?? null : null;
}

function getTable(uuid: string): Table | null {
    return floor.tables.find((table) => table.uuid === uuid) ?? null;
}

function seatsLabel(seats: number): string {
    return seats === 1 ? '1 seat' : `${seats} seats`;
}

(<any>window).init                = init;
(<any>window).renderSidebar       = renderSidebar;
(<any>window).toggleSidebar       = toggleSidebar;
(<any>window).switchTab           = switchTab;
(<any>window).openCreateModal     = openCreateModal;
(<any>window).saveRestaurant      = saveRestaurant;
(<any>window).openCreateRoomModal = openCreateRoomModal;
(<any>window).createRoom          = createRoom;
(<any>window).deleteRoom          = deleteRoom;
(<any>window).saveTable           = saveTable;
(<any>window).saveCustomer        = saveCustomer;
(<any>window).saveEmployee        = saveEmployee;
(<any>window).openDeleteModal     = openDeleteModal;
(<any>window).cancelDelete        = cancelDelete;
(<any>window).confirmDelete       = confirmDelete;
(<any>window).openModal           = openModal;
(<any>window).closeModal          = closeModal;
