import {getData} from "../../../main";
import {openModal, init as initModals, closeModal} from "../../../components/modal";
import {postOnce, restoreSidebar, setField, setupDropTarget, showWhileDragging, submitForm, toggleSidebar} from "./shared";
import {initKitchen} from "./kitchen";
import {cloneTemplate, pluralize} from "./kitchen/elements";
import {toggleMenu} from "./kitchen/menu";
import {describeOrder, openOrderDetails} from "./kitchen/order-details";
import {getAddButton, initSidebar, KitchenTab, renderKitchenTab, renderSidebarList} from "./kitchen/sidebar";
import {
    canControl, getServiceState, isIdle, isPlayer, mayWait, mayWaitAny, onServiceState, post as postService, refreshServiceState,
} from "./kitchen/state";
import {assignWaiter, getTableOrders, getWaiter, renderTableService} from "./kitchen/tables";
import {Order} from "./kitchen/types";
import {isOrderModalOpen, openOrderModal, SeatedCustomer} from "./orders";
import "./shift";

interface Room {
    uuid: string;
    name: string;
}

interface Table {
    uuid: string;
    name: string;
    capacity: number;
    room_uuid: string | null;
}

interface Customer {
    uuid: string;
    name: string;
    description: string | null;
    /** Both null while the customer is not seated */
    table_uuid: string | null;
    seat: number | null;
    /** Waiting at the entrance to be seated, since when in the restaurant's time */
    at_entrance: boolean;
    entered_at: string | null;
    /** Where a waiter is taking them from the entrance, while they are on their way there */
    seating: {table_uuid: string, seat: number} | null;
}

interface Floor {
    rooms: Room[];
    tables: Table[];
    customers: Customer[];
    /** How many changes the restaurant had seen, when fetched by itself rather than returned by a change */
    version?: number;
}

/** Where a dragged customer comes from: the entrance, or the seat they are seated on */
interface DraggedCustomer {
    uuid: string;
    seated: boolean;
}

const CUSTOMER_MIME = 'application/x-restaurant-customer';

/** The sidebar lists the customers, and what comes with the kitchen's state */
type SidebarTab = 'customer' | KitchenTab;
let activeTab: SidebarTab = 'customer';

let apiUrl = '';
let floor: Floor = {rooms: [], tables: [], customers: []};

/** The customers waiting at the entrance, in order of arrival */
function getEntrance(): Customer[] {
    return floor.customers.filter((customer) => customer.at_entrance)
        .sort((a, b) => (a.entered_at ?? '').localeCompare(b.entered_at ?? ''));
}

//| Initialisation

async function init(): Promise<void> {
    const root = document.getElementById('service') as HTMLElement;
    apiUrl = root.dataset.apiUrl!;

    initModals();
    initSidebar();
    const kitchen = initKitchen(root.dataset.serviceUrl!);

    // Tables show their orders, and the sidebar the ingredients, which come with the kitchen's state
    onServiceState(() => {
        renderFloor();
        if (activeTab !== 'customer') renderSidebar();
        offerTakenOrder();
    });

    // During service the floor and kitchen need the room, so the sidebar starts closed. Players only see conversions
    restoreSidebar('dnd-restaurant-service-sidebar-collapsed', true);
    if (root.dataset.player === '1') {
        activeTab = 'conversion';
    }

    setupDropTarget(document.querySelector('#service-entrance') as HTMLElement, CUSTOMER_MIME, (data) => {
        const customer = JSON.parse(data) as DraggedCustomer;
        if (customer.seated) {
            seatCustomer(customer.uuid, null, null, true);
        }
    }, () => !isPlayer());

    const edge = document.getElementById('discard-edge') as HTMLElement;
    setupDropTarget(edge, CUSTOMER_MIME, (data) => {
        const customer = JSON.parse(data) as DraggedCustomer;
        if (customer.seated) {
            seatCustomer(customer.uuid, null, null, false);
        } else {
            moveToEntrance(customer.uuid, false);
        }
    }, () => !isPlayer());
    showWhileDragging(edge, CUSTOMER_MIME);
    showWhileDragging(document.getElementById('service-entrance') as HTMLElement, CUSTOMER_MIME);

    const response = await getData(apiUrl);
    setFloor(response.data);
    await kitchen;

    // Customers are seated by others as well, so the floor is refreshed along with the kitchen
    setInterval(refreshFloor, FLOOR_REFRESH_INTERVAL);
}

/** How often the floor is refreshed, in milliseconds */
const FLOOR_REFRESH_INTERVAL = 5000;

async function refreshFloor(): Promise<void> {
    if (!isIdle()) return;

    // Nothing comes back but that it is unchanged, while nothing changed since the floor was last fetched
    const query = new FormData();
    if (floor.version !== undefined) query.append('version', String(floor.version));

    try {
        const response = await getData(apiUrl, query);
        if (isIdle() && !response.data.unchanged) setFloor(response.data);
    } catch {
        // Tried again on the next refresh
    }
}

function setFloor(data: Floor): void {
    floor = data;
    render();
}

//| Rendering

function render(): void {
    renderSidebar();
    renderEntrance();
    renderFloor();
}

function renderSidebar(): void {
    if (activeTab !== 'customer') {
        renderKitchenTab(activeTab);
        return;
    }

    renderSidebarList(floor.customers, (customer) => customer.name, 'No customers yet', (row, customer) => {
        setField(row, 'meta', describeLocation(customer));
        row.title = customer.description ?? '';

        // Only customers that aren't on the floor yet can be brought to the entrance
        const addButton = getAddButton(row);
        addButton.classList.toggle('hidden', customer.table_uuid !== null || customer.at_entrance);
        addButton.addEventListener('click', () => moveToEntrance(customer.uuid, true));
    });
}

function switchTab(tab: SidebarTab): void {
    activeTab = tab;
    document.querySelectorAll<HTMLElement>('.inventory-tab[data-tab]').forEach((button) => {
        button.classList.toggle('active', button.dataset.tab === tab);
    });

    // New ingredients are made on the management page, so only customers can be created here
    document.getElementById('customer-create-button')?.classList.toggle('hidden', tab !== 'customer');
    renderSidebar();
}

function renderEntrance(): void {
    const container = document.querySelector('#service-entrance [data-items]') as HTMLElement;
    container.replaceChildren();

    const waiting = getEntrance();
    container.append(...waiting.map(createCustomerChip));

    // An empty entrance is hidden, though it still shows while a customer is dragged so they can be put back there
    document.getElementById('service-entrance')!.classList.toggle('empty', waiting.length === 0);
}

function renderFloor(): void {
    const container = document.getElementById('service-rooms') as HTMLElement;
    container.replaceChildren();

    for (const room of floor.rooms) {
        const tables = floor.tables.filter((table) => table.room_uuid === room.uuid);
        if (tables.length > 0) {
            container.appendChild(createRoomSection(room.name, tables));
        }
    }

    // Tables that haven't been placed in a room can still be served
    const unplaced = floor.tables.filter((table) => table.room_uuid === null);
    if (unplaced.length > 0) {
        container.appendChild(createRoomSection('Not in a room', unplaced));
    }

    document.getElementById('service-empty')!.classList.toggle('hidden', floor.tables.length > 0);
}

function createRoomSection(name: string, tables: Table[]): HTMLElement {
    const section = cloneTemplate('room-template');

    const capacity = tables.reduce((total, table) => total + table.capacity, 0);
    const seated = floor.customers.filter((customer) => tables.some((table) => table.uuid === customer.table_uuid)).length;
    setField(section, 'name', name);
    setField(section, 'occupancy', `${seated} / ${capacity} seated`);

    const container = section.querySelector('[data-tables]') as HTMLElement;
    for (const table of tables) {
        container.appendChild(createTableElement(table));
    }

    return section;
}

//| Tables

function createTableElement(table: Table): HTMLElement {
    const element = cloneTemplate('table-template');
    const seatedHere = floor.customers.filter((customer) => customer.table_uuid === table.uuid);

    setField(element, 'name', table.name);
    setField(element, 'occupancy', `${seatedHere.length} / ${table.capacity}`);

    const seats = element.querySelector('[data-seats]') as HTMLElement;
    for (let number = 1; number <= table.capacity; number++) {
        const occupant = seatedHere.find((customer) => customer.seat === number) ?? null;
        seats.appendChild(createSeatElement(table, number, occupant));
    }

    // Clicking the table, rather than one of its seats, opens its menu. Players open the menus of the tables their
    // employees wait
    element.addEventListener('click', (event) => {
        if (!mayWait(table.uuid) || (event.target as Element).closest('.service-seat') !== null) return;
        openTableMenu(table, element);
    });

    renderTableService(element, table);
    return element;
}

function openTableMenu(table: Table, anchor: HTMLElement): void {
    const orders = getTableOrders(table);
    const status = orders.length === 0 ? table.name : `${table.name}: ${pluralize(orders.length, 'order', 'orders')}`;

    const orderTable = {uuid: table.uuid, name: table.name};
    const waiter = getWaiter(table);

    toggleMenu(anchor, waiter !== null ? `${status} · ${waiter.name}` : `${status} · no waiter`, (option) => {
        // Orders are taken by the table's waiter, one at a time, and noted down by whoever runs the service once they
        // have taken it. Players send their waiter to take one, and move their own employees around
        const taken = orders.find((order) => order.status === 'taken');
        if (taken !== undefined && !isPlayer()) {
            option('Note down order', () => openOrderModal(orderTable, getSeatedCustomers(table), taken));
        }
        if (waiter !== null) {
            if (!orders.some((order) => order.status === 'taking' || order.status === 'taken')) {
                option('Take order', () => startOrder(table));
            }
            if (canControl(waiter)) {
                option(`Remove ${waiter.name}`, () => assignWaiter(table, null));
            }
        }

        for (const order of orders) {
            option(`Order of ${order.ordered_at} — ${describeOrder(order)}`,
                () => openOrderDetails(order, orderTable, getSeatedCustomers(table)));
        }
    });
}

//| Taking orders

/** The taken orders whose modal was closed without noting them down, which aren't offered again until reloading */
const putOffOrders = new Set<string>();
/** The taken order the order modal was opened for by itself, to tell when it was closed without being noted down */
let offeredOrder: string | null = null;

/** Sends the table's waiter to take an order, after which what was ordered is noted down */
async function startOrder(table: Table): Promise<void> {
    await postService('/order', JSON.stringify({table_uuid: table.uuid}));
}

/**
 * Opens the order modal for an order the waiter is done taking, so what was ordered can be noted down. Closing it
 * puts that order off, after which it is still noted down from the table's menu. Only whoever runs the service notes
 * orders down, and never on top of another modal
 */
function offerTakenOrder(): void {
    if (isPlayer()) return;

    const orders = getServiceState()?.orders ?? [];
    if (offeredOrder !== null && !isOrderModalOpen()) {
        if (orders.some((order) => order.uuid === offeredOrder && order.status === 'taken')) {
            putOffOrders.add(offeredOrder);
        }
        offeredOrder = null;
    }

    if (document.querySelector('.modal:not(.hidden)') !== null) return;

    const order = orders.find((candidate: Order) => candidate.status === 'taken' && !putOffOrders.has(candidate.uuid));
    const table = order !== undefined ? floor.tables.find((candidate) => candidate.uuid === order.table_uuid) : undefined;
    if (order === undefined || table === undefined) return;

    offeredOrder = order.uuid;
    openOrderModal({uuid: table.uuid, name: table.name}, getSeatedCustomers(table), order);
}

/** The customers seated at a table, in the order of their seats */
function getSeatedCustomers(table: Table): SeatedCustomer[] {
    return floor.customers
        .filter((customer) => customer.table_uuid === table.uuid)
        .sort((a, b) => (a.seat ?? 0) - (b.seat ?? 0))
        .map((customer) => ({uuid: customer.uuid, name: customer.name}));
}

function createSeatElement(table: Table, number: number, occupant: Customer | null): HTMLElement {
    const seat = cloneTemplate('seat-template');
    setField(seat, 'number', String(number));
    seat.title = `${table.name}, seat ${number}`;

    if (occupant !== null) {
        seat.classList.add('occupied');
        seat.appendChild(createCustomerChip(occupant));
    }

    // A seat is kept for a customer a waiter is bringing over
    const coming = floor.customers.find((customer) => customer.seating?.table_uuid === table.uuid && customer.seating.seat === number) ?? null;
    if (occupant === null && coming !== null) {
        seat.classList.add('reserved');
        seat.title = `${table.name}, seat ${number}: kept for ${coming.name}`;
    }

    // Only free seats take customers, who players seat at the tables their employees wait
    setupDropTarget(seat, CUSTOMER_MIME, (data) => {
        const customer = JSON.parse(data) as DraggedCustomer;
        seatCustomer(customer.uuid, table.uuid, number, false);
    }, () => !seat.classList.contains('occupied') && !seat.classList.contains('reserved') && mayWait(table.uuid));

    return seat;
}

function createCustomerChip(customer: Customer): HTMLElement {
    const chip = cloneTemplate('customer-chip-template');

    setField(chip, 'name', customer.name);
    chip.title = customer.description ?? customer.name;

    // A customer a waiter is bringing over shows where they are going
    const seating = customer.seating;
    if (seating !== null) {
        const table = floor.tables.find((candidate) => candidate.uuid === seating.table_uuid);
        setField(chip, 'name', `${customer.name} → ${table?.name ?? 'a table'}`);
        chip.classList.add('on-the-way');
    }

    // Customers are seated by whoever runs the floor, and by players from the entrance at the tables they wait
    if (isPlayer() && !(customer.at_entrance && mayWaitAny())) {
        chip.draggable = false;
        return chip;
    }

    chip.addEventListener('dragstart', (event) => {
        const data: DraggedCustomer = {uuid: customer.uuid, seated: customer.table_uuid !== null};
        event.dataTransfer!.setData(CUSTOMER_MIME, JSON.stringify(data));
        event.dataTransfer!.effectAllowed = 'move';
    });

    return chip;
}

//| Entrance

/** Brings a customer to the entrance to wait there, or sends them off the floor */
async function moveToEntrance(uuid: string, atEntrance: boolean): Promise<void> {
    const formData = new FormData();
    formData.append('at_entrance', atEntrance ? '1' : '0');

    try {
        const response = await postOnce(`${apiUrl}/customer/${uuid}/entrance`, formData);
        if (response?.ok) setFloor(response.data.floor);
    } catch {
        // The error has already been shown to the user
    }
}

//| Seating

/**
 * Seats a customer on a seat, or unseats them when no table is given
 * @param toEntrance Whether an unseated customer goes back to the entrance, rather than leaving the floor
 */
async function seatCustomer(uuid: string, tableUuid: string | null, seat: number | null, toEntrance: boolean): Promise<void> {
    const formData = new FormData();
    if (tableUuid !== null && seat !== null) {
        formData.append('table_uuid', tableUuid);
        formData.append('seat', String(seat));
    } else if (toEntrance) {
        formData.append('to_entrance', '1');
    }

    try {
        const response = await postOnce(`${apiUrl}/customer/${uuid}/seat`, formData);
        if (!response?.ok) return;

        response.announce();
        setFloor(response.data.floor);
        // A waiter seating them shows in the kitchen's state, so it is fetched along with the floor
        await refreshServiceState();
    } catch {
        // The error has already been shown to the user
    }
}

//| Customers

function openCreateCustomerModal(): void {
    (document.getElementById('customer-create-form') as HTMLFormElement).reset();
    openModal('customer-create-modal');
}

async function createCustomer(): Promise<void> {
    const form = document.getElementById('customer-create-form') as HTMLFormElement;
    if (!form.reportValidity()) return;

    const response = await submitForm(form, `${apiUrl}/customer/create`);
    if (response === null) return;

    closeModal('customer-create-modal');
    setFloor(response.floor);
}

//| Helpers

function getCustomer(uuid: string): Customer | null {
    return floor.customers.find((customer) => customer.uuid === uuid) ?? null;
}

function describeLocation(customer: Customer): string {
    if (customer.table_uuid !== null) {
        const table = floor.tables.find((table) => table.uuid === customer.table_uuid);
        return `Seated at ${table?.name ?? 'a table'}, seat ${customer.seat}`;
    }

    if (customer.seating !== null) {
        const table = floor.tables.find((table) => table.uuid === customer.seating!.table_uuid);
        return `On the way to ${table?.name ?? 'a table'}`;
    }

    return customer.at_entrance ? 'At the entrance' : 'Not here';
}

(<any>window).init                    = init;
(<any>window).renderSidebar           = renderSidebar;
(<any>window).switchTab               = switchTab;
(<any>window).toggleSidebar           = toggleSidebar;
(<any>window).openCreateCustomerModal = openCreateCustomerModal;
(<any>window).createCustomer          = createCustomer;
(<any>window).openModal               = openModal;
(<any>window).closeModal              = closeModal;
