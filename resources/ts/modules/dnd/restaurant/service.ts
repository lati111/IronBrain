import {getData} from "../../../main";
import {openModal, init as initModals, closeModal} from "../../../components/modal";
import {
    formatMoney, postOnce, refreshMoneyInputs, restoreSidebar, setField, setupDropTarget, setupMoneyInput, showWhileDragging,
    submitForm, toggleSidebar,
} from "./shared";
import {
    canControl, createEmployeeIcon, describeEmployee, Employee, EMPLOYEE_MIME, formatMinutes, getDraggedEmployee, getDraggedPlate,
    getServiceState, initKitchen, isIdle, isPlayer, KitchenRecipe, onRightClick, onServiceState, openPrioritizeMenu, Order, PLATE_MIME,
    post as postService, ServiceConversion, ServiceIngredient, setupEmployeeDrag, toggleMenu,
} from "./kitchen";
import {describeOrder, openOrderDetails, openOrderModal} from "./orders";
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
}

interface Floor {
    rooms: Room[];
    tables: Table[];
    customers: Customer[];
}

/** Where a dragged customer comes from: the entrance, or the seat they are seated on */
interface DraggedCustomer {
    uuid: string;
    seated: boolean;
}

const CUSTOMER_MIME = 'application/x-restaurant-customer';

/** The sidebar lists the customers, the ingredients that can be brought in during service, the conversions that can be
 * done in the prep area, and the recipes' sales */
type SidebarTab = 'customer' | 'ingredient' | 'conversion' | 'sale';
let activeTab: SidebarTab = 'customer';

/** The ingredient the pile modal is adding */
let pileIngredient: ServiceIngredient | null = null;

/** The conversion the conversion modal queues */
let pendingConversion: ServiceConversion | null = null;

/** The recipe the sale modal is for */
let saleRecipe: KitchenRecipe | null = null;

let apiUrl = '';
let floor: Floor = {rooms: [], tables: [], customers: []};

/** The customers waiting at the entrance, in order of arrival. Only exists client side, like general storage */
const entrance: string[] = [];

//| Initialisation

async function init(): Promise<void> {
    const root = document.getElementById('service') as HTMLElement;
    apiUrl = root.dataset.apiUrl!;

    initModals();
    setupMoneyInput(document.getElementById('sale-price') as HTMLInputElement);
    const kitchen = initKitchen(root.dataset.serviceUrl!);

    // Tables show their orders, and the sidebar the ingredients, which come with the kitchen's state
    onServiceState(() => {
        renderFloor();
        if (activeTab !== 'customer') renderSidebar();
    });

    // During service the floor and kitchen need the room, so the customer list starts closed. Players have none
    if (document.getElementById('inventory-sidebar') !== null) {
        restoreSidebar('dnd-restaurant-service-sidebar-collapsed', true);
    }

    setupDropTarget(document.querySelector('#service-entrance') as HTMLElement, CUSTOMER_MIME, (data) => {
        const customer = JSON.parse(data) as DraggedCustomer;
        if (customer.seated) {
            seatCustomer(customer.uuid, null, null, true);
        }
    });

    const edge = document.getElementById('discard-edge') as HTMLElement;
    setupDropTarget(edge, CUSTOMER_MIME, (data) => {
        const customer = JSON.parse(data) as DraggedCustomer;
        if (customer.seated) {
            seatCustomer(customer.uuid, null, null, false);
        } else {
            leaveEntrance(customer.uuid);
        }
    });
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

    try {
        const response = await getData(apiUrl);
        if (isIdle()) setFloor(response.data);
    } catch {
        // Tried again on the next refresh
    }
}

function setFloor(data: Floor): void {
    floor = data;

    // Customers that are seated or no longer exist can't be waiting at the entrance
    for (let index = entrance.length - 1; index >= 0; index--) {
        const customer = getCustomer(entrance[index]);
        if (customer === null || customer.table_uuid !== null) {
            entrance.splice(index, 1);
        }
    }

    render();
}

//| Rendering

function render(): void {
    renderSidebar();
    renderEntrance();
    renderFloor();
}

function renderSidebar(): void {
    // Players have no sidebar
    if (document.getElementById('inventory-list') === null) return;

    if (activeTab === 'ingredient') {
        renderIngredientRows();
        return;
    }

    if (activeTab === 'sale') {
        renderSaleRows();
        return;
    }

    if (activeTab === 'conversion') {
        renderConversionRows();
        return;
    }

    const list = document.getElementById('inventory-list') as HTMLElement;
    const search = (document.getElementById('inventory-search') as HTMLInputElement).value.trim().toLowerCase();
    const template = document.getElementById('list-row-template') as HTMLTemplateElement;

    list.replaceChildren();
    for (const customer of floor.customers.filter((customer) => customer.name.toLowerCase().includes(search))) {
        const row = template.content.firstElementChild!.cloneNode(true) as HTMLElement;
        setField(row, 'name', customer.name);
        setField(row, 'meta', describeLocation(customer));
        row.title = customer.description ?? '';

        // Only customers that aren't on the floor yet can be brought to the entrance
        const addButton = row.querySelector('[data-action="add"]') as HTMLElement;
        const onFloor = customer.table_uuid !== null || entrance.includes(customer.uuid);
        addButton.classList.toggle('hidden', onFloor);
        addButton.addEventListener('click', () => enterEntrance(customer.uuid));

        list.appendChild(row);
    }

    if (list.childElementCount === 0) {
        const empty = document.createElement('li');
        empty.className = 'text-center text-gray-400 py-4 text-sm';
        empty.textContent = floor.customers.length === 0 ? 'No customers yet' : 'No results';
        list.appendChild(empty);
    }
}

/** Lists the ingredients, each of which can be added to the pile of ingredients brought in during service */
function renderIngredientRows(): void {
    const list = document.getElementById('inventory-list') as HTMLElement;
    const search = (document.getElementById('inventory-search') as HTMLInputElement).value.trim().toLowerCase();
    const template = document.getElementById('list-row-template') as HTMLTemplateElement;
    const ingredients = getServiceState()?.ingredients ?? [];

    list.replaceChildren();
    for (const ingredient of ingredients.filter((ingredient) => ingredient.name.toLowerCase().includes(search))) {
        const row = template.content.firstElementChild!.cloneNode(true) as HTMLElement;
        setField(row, 'name', ingredient.name);
        setField(row, 'meta', ingredient.pile > 0
            ? `${ingredient.stored} stored · ${ingredient.pile} unsorted`
            : `${ingredient.stored} stored`);

        const addButton = row.querySelector('[data-action="add"]') as HTMLElement;
        addButton.title = 'Add';
        addButton.addEventListener('click', () => openPileModal(ingredient));

        list.appendChild(row);
    }

    if (list.childElementCount === 0) {
        const empty = document.createElement('li');
        empty.className = 'text-center text-gray-400 py-4 text-sm';
        empty.textContent = ingredients.length === 0 ? 'No ingredients yet' : 'No results';
        list.appendChild(empty);
    }
}

/** Lists the conversions, each of which can be queued in the prep area */
function renderConversionRows(): void {
    const list = document.getElementById('inventory-list') as HTMLElement;
    const search = (document.getElementById('inventory-search') as HTMLInputElement).value.trim().toLowerCase();
    const template = document.getElementById('list-row-template') as HTMLTemplateElement;
    const conversions = getServiceState()?.conversions ?? [];

    list.replaceChildren();
    for (const conversion of conversions) {
        const name = `${conversion.input_name} → ${conversion.output_amount} ${conversion.output_name}`;
        if (!name.toLowerCase().includes(search)) continue;

        const row = template.content.firstElementChild!.cloneNode(true) as HTMLElement;
        setField(row, 'name', name);
        setField(row, 'meta', `${conversion.available} stored · ${formatMinutes(conversion.minutes)} each`);

        const addButton = row.querySelector('[data-action="add"]') as HTMLElement;
        addButton.title = 'Convert';
        addButton.addEventListener('click', () => openConversionModal(conversion));

        list.appendChild(row);
    }

    if (list.childElementCount === 0) {
        const empty = document.createElement('li');
        empty.className = 'text-center text-gray-400 py-4 text-sm';
        empty.textContent = conversions.length === 0 ? 'No conversions yet' : 'No results';
        list.appendChild(empty);
    }
}

function openConversionModal(conversion: ServiceConversion): void {
    pendingConversion = conversion;
    document.getElementById('conversion-modal-title')!.textContent = `Convert ${conversion.input_name} into ${conversion.output_name}`;

    const input = document.getElementById('conversion-amount') as HTMLInputElement;
    input.value = '1';
    input.max = conversion.available > 0 ? String(conversion.available) : '';
    openModal('conversion-modal');
    input.focus();
    input.select();
}

async function queueConversion(): Promise<void> {
    const form = document.getElementById('conversion-form') as HTMLFormElement;
    if (pendingConversion === null || !form.reportValidity()) return;

    if (await postService(`/conversion/${pendingConversion.uuid}/queue`, new FormData(form))) {
        pendingConversion = null;
        closeModal('conversion-modal');
    }
}

/** Lists the recipes with their price, and their sale price for this service when they are on sale */
function renderSaleRows(): void {
    const list = document.getElementById('inventory-list') as HTMLElement;
    const search = (document.getElementById('inventory-search') as HTMLInputElement).value.trim().toLowerCase();
    const template = document.getElementById('list-row-template') as HTMLTemplateElement;
    const recipes = getServiceState()?.recipes ?? [];

    list.replaceChildren();
    for (const recipe of recipes.filter((recipe) => recipe.name.toLowerCase().includes(search))) {
        const row = template.content.firstElementChild!.cloneNode(true) as HTMLElement;
        row.classList.add('clickable');
        row.classList.toggle('on-sale', recipe.on_sale);
        setField(row, 'name', recipe.name);
        setField(row, 'meta', recipe.on_sale
            ? `${formatMoney(recipe.base_price)} → ${formatMoney(recipe.price)}`
            : formatMoney(recipe.base_price));

        // The whole row opens the sale, so the add button isn't needed
        row.querySelector('[data-action="add"]')!.classList.add('hidden');
        row.addEventListener('click', () => openSaleModal(recipe));

        list.appendChild(row);
    }

    if (list.childElementCount === 0) {
        const empty = document.createElement('li');
        empty.className = 'text-center text-gray-400 py-4 text-sm';
        empty.textContent = recipes.length === 0 ? 'No recipes yet' : 'No results';
        list.appendChild(empty);
    }
}

function openSaleModal(recipe: KitchenRecipe): void {
    saleRecipe = recipe;
    document.getElementById('sale-modal-title')!.textContent = `${recipe.name} on sale`;
    document.getElementById('sale-remove')!.classList.toggle('hidden', !recipe.on_sale);

    const input = document.getElementById('sale-price') as HTMLInputElement;
    input.value = String(recipe.price);
    refreshMoneyInputs(input.form!);
    openModal('sale-modal');
}

async function saveSale(): Promise<void> {
    const form = document.getElementById('sale-form') as HTMLFormElement;
    if (saleRecipe === null || !form.reportValidity()) return;

    if (await postService(`/sale/${saleRecipe.uuid}`, new FormData(form))) {
        saleRecipe = null;
        closeModal('sale-modal');
    }
}

async function removeSale(): Promise<void> {
    if (saleRecipe === null) return;

    if (await postService(`/sale/${saleRecipe.uuid}/remove`)) {
        saleRecipe = null;
        closeModal('sale-modal');
    }
}

function switchTab(tab: SidebarTab): void {
    activeTab = tab;
    document.querySelectorAll<HTMLElement>('.inventory-tab[data-tab]').forEach((button) => {
        button.classList.toggle('active', button.dataset.tab === tab);
    });

    // New ingredients are made on the management page, so only customers can be created here
    document.getElementById('customer-create-button')!.classList.toggle('hidden', tab !== 'customer');
    renderSidebar();
}

function openPileModal(ingredient: ServiceIngredient): void {
    pileIngredient = ingredient;
    document.getElementById('pile-modal-title')!.textContent = `Add ${ingredient.name}`;

    const input = document.getElementById('pile-amount') as HTMLInputElement;
    input.value = '1';
    openModal('pile-modal');
    input.focus();
    input.select();
}

async function addToPile(): Promise<void> {
    const form = document.getElementById('pile-form') as HTMLFormElement;
    if (pileIngredient === null || !form.reportValidity()) return;

    if (await postService(`/ingredient/${pileIngredient.uuid}/add`, new FormData(form))) {
        pileIngredient = null;
        closeModal('pile-modal');
    }
}

function renderEntrance(): void {
    const container = document.querySelector('#service-entrance [data-items]') as HTMLElement;
    container.replaceChildren();

    for (const uuid of entrance) {
        const customer = getCustomer(uuid);
        if (customer !== null) {
            container.appendChild(createCustomerChip(customer));
        }
    }

    // An empty entrance is hidden, though it still shows while a customer is dragged so they can be put back there
    document.getElementById('service-entrance')!.classList.toggle('empty', entrance.length === 0);
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
    const template = document.getElementById('room-template') as HTMLTemplateElement;
    const section = template.content.firstElementChild!.cloneNode(true) as HTMLElement;

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

function createTableElement(table: Table): HTMLElement {
    const template = document.getElementById('table-template') as HTMLTemplateElement;
    const element = template.content.firstElementChild!.cloneNode(true) as HTMLElement;
    const seatedHere = floor.customers.filter((customer) => customer.table_uuid === table.uuid);

    setField(element, 'name', table.name);
    setField(element, 'occupancy', `${seatedHere.length} / ${table.capacity}`);

    const seats = element.querySelector('[data-seats]') as HTMLElement;
    for (let number = 1; number <= table.capacity; number++) {
        const occupant = seatedHere.find((customer) => customer.seat === number) ?? null;
        seats.appendChild(createSeatElement(table, number, occupant));
    }

    const orders = getTableOrders(table);
    renderOrderIndicator(element, orders);
    renderWaiter(element, table);
    renderWaiterJob(element, table);

    // Clicking the table, rather than one of its seats, opens its menu. Players only move their employees around
    element.addEventListener('click', (event) => {
        if (isPlayer() || (event.target as Element).closest('.service-seat') !== null) return;
        openTableMenu(table, element);
    });

    // A plated portion can be brought to the table when it is still waiting for one of that recipe
    setupDropTarget(element, PLATE_MIME, (uuid) => {
        const formData = new FormData();
        formData.append('table_uuid', table.uuid);
        postService(`/plate/${uuid}/deliver`, formData);
    }, () => {
        // Only items without a plate on its way yet, and only when someone waits the table to bring it
        const plate = getDraggedPlate();
        return !isPlayer() && plate !== null && getWaiter(table) !== null && orders.some((order) => order.status === 'open'
            && order.items.some((item) => !item.served && !item.on_the_way && item.recipe_uuid === plate.recipe_uuid));
    });

    // An employee dropped on the table waits it, taking its orders. Players can't take a table from someone else's waiter
    setupDropTarget(element, EMPLOYEE_MIME, (uuid) => assignWaiter(table, uuid), () => {
        const employee = getDraggedEmployee();
        const waiter = getWaiter(table);
        return employee !== null && !employee.tables.includes(table.uuid) && (waiter === null || canControl(waiter));
    });

    return element;
}

//| Waiters

/** The employee waiting a table, who takes its orders */
function getWaiter(table: Table): Employee | null {
    return getServiceState()?.employees.find((employee) => employee.tables.includes(table.uuid)) ?? null;
}

/** Shows who waits the table, whose icon can be dragged to another table or back to the staff */
function renderWaiter(element: HTMLElement, table: Table): void {
    const slot = element.querySelector('[data-waiter]') as HTMLElement;
    slot.replaceChildren();

    const waiter = getWaiter(table);
    if (waiter !== null) {
        const icon = createEmployeeIcon(waiter);
        icon.title = describeEmployee(waiter);
        if (canControl(waiter)) {
            icon.draggable = true;
            setupEmployeeDrag(icon, waiter);
        }
        slot.appendChild(icon);
    }
}

/** Shows the job the waiter is on at the table, with its progress and how long it takes until it is done */
function renderWaiterJob(element: HTMLElement, table: Table): void {
    const container = element.querySelector('[data-waiter-job]') as HTMLElement;
    const jobs = (getServiceState()?.waiting ?? []).filter((job) => job.table_uuid === table.uuid);
    container.classList.toggle('hidden', jobs.length === 0);
    if (jobs.length === 0) return;

    const job = jobs[0];
    const more = jobs.length > 1 ? ` (+${jobs.length - 1})` : '';
    const time = job.minutes_left !== null ? formatMinutes(job.minutes_left) : 'no waiter';
    setField(container, 'job', `${job.task.replace(/ (at|to) .*$/, '')}${more} · ${time}`);
    container.title = jobs.map((candidate) => candidate.task).join('\n');
    (container.querySelector('[data-waiter-job-fill]') as HTMLElement).style.width = `${Math.round(job.progress * 100)}%`;

    // Any of the table's jobs can be moved to the front of its waiter's queue, by whoever runs the floor
    if (isPlayer()) return;
    onRightClick(container, () => openPrioritizeMenu(container, `${table.name}: waiter`,
        jobs.map((candidate) => candidate.uuid),
        jobs.length > 1 ? jobs.map((candidate) => candidate.task.replace(/ (at|to) .*$/, '').toLowerCase()) : []));
}

async function assignWaiter(table: Table, employeeUuid: string | null): Promise<void> {
    const formData = new FormData();
    if (employeeUuid !== null) formData.append('employee_uuid', employeeUuid);
    await postService(`/table/${table.uuid}/waiter`, formData, false);
}

//| Orders

function getTableOrders(table: Table): Order[] {
    return (getServiceState()?.orders ?? []).filter((order) => order.table_uuid === table.uuid);
}

/**
 * Shows how long ago the table ordered, or once everything is served, how long ago that was
 */
function renderOrderIndicator(element: HTMLElement, orders: Order[]): void {
    const indicator = element.querySelector('[data-field="order"]') as HTMLElement;
    // Orders still being taken wait as well
    const waiting = orders.filter((order) => order.status === 'open' || order.status === 'taking');
    const fulfilled = orders.filter((order) => order.status === 'fulfilled');

    indicator.classList.toggle('waiting', waiting.length > 0);
    indicator.classList.toggle('fulfilled', waiting.length === 0 && fulfilled.length > 0);

    if (waiting.length > 0) {
        // The longest waiting order is the one that matters most
        indicator.textContent = `Ordered ${formatMinutes(Math.max(...waiting.map((order) => order.ordered_minutes_ago)))} ago`;
        indicator.title = waiting.map(describeOrder).join('\n');
    } else if (fulfilled.length > 0) {
        indicator.textContent = `Served ${formatMinutes(Math.min(...fulfilled.map((order) => order.fulfilled_minutes_ago ?? 0)))} ago`;
        indicator.title = fulfilled.map(describeOrder).join('\n');
    } else {
        indicator.textContent = '';
        indicator.title = '';
    }

    renderOrderProgress(element, [...waiting, ...fulfilled]);
}

/** Fills the table's completion bar with how many of the portions ordered there have been served */
function renderOrderProgress(element: HTMLElement, orders: Order[]): void {
    const progress = element.querySelector('[data-order-progress]') as HTMLElement;
    const items = orders.flatMap((order) => order.items);
    const served = items.filter((item) => item.served).length;

    progress.classList.toggle('hidden', items.length === 0);
    progress.classList.toggle('complete', items.length > 0 && served === items.length);
    progress.title = `${served} of ${items.length} ${items.length === 1 ? 'portion' : 'portions'} served`;
    (progress.querySelector('[data-order-progress-fill]') as HTMLElement).style.width =
        items.length === 0 ? '0%' : `${(served / items.length) * 100}%`;
    setField(progress, 'order-progress', `${served}/${items.length}`);
}

function openTableMenu(table: Table, anchor: HTMLElement): void {
    const orders = getTableOrders(table);
    const status = orders.length === 0 ? table.name : `${table.name}: ${orders.length} ${orders.length === 1 ? 'order' : 'orders'}`;

    const orderTable = {uuid: table.uuid, name: table.name};
    const waiter = getWaiter(table);

    toggleMenu(anchor, waiter !== null ? `${status} · ${waiter.name}` : `${status} · no waiter`, (option) => {
        // Orders are taken by the table's waiter
        if (waiter !== null) {
            option('Take order', () => openOrderModal(orderTable, getSeatedCustomers(table)));
            option(`Remove ${waiter.name}`, () => assignWaiter(table, null));
        }

        for (const order of orders) {
            option(`Order of ${order.ordered_at} — ${describeOrder(order)}`,
                () => openOrderDetails(order, orderTable, getSeatedCustomers(table)));
        }
    });
}

/** The customers seated at a table, in the order of their seats */
function getSeatedCustomers(table: Table): {uuid: string, name: string}[] {
    return floor.customers
        .filter((customer) => customer.table_uuid === table.uuid)
        .sort((a, b) => (a.seat ?? 0) - (b.seat ?? 0))
        .map((customer) => ({uuid: customer.uuid, name: customer.name}));
}

function createSeatElement(table: Table, number: number, occupant: Customer | null): HTMLElement {
    const template = document.getElementById('seat-template') as HTMLTemplateElement;
    const seat = template.content.firstElementChild!.cloneNode(true) as HTMLElement;
    setField(seat, 'number', String(number));
    seat.title = `${table.name}, seat ${number}`;

    if (occupant !== null) {
        seat.classList.add('occupied');
        seat.appendChild(createCustomerChip(occupant));
    }

    // Only empty seats take customers
    setupDropTarget(seat, CUSTOMER_MIME, (data) => {
        const customer = JSON.parse(data) as DraggedCustomer;
        seatCustomer(customer.uuid, table.uuid, number, false);
    }, () => !seat.classList.contains('occupied'));

    return seat;
}

function createCustomerChip(customer: Customer): HTMLElement {
    const template = document.getElementById('customer-chip-template') as HTMLTemplateElement;
    const chip = template.content.firstElementChild!.cloneNode(true) as HTMLElement;

    setField(chip, 'name', customer.name);
    chip.title = customer.description ?? customer.name;

    // Customers are seated by whoever runs the floor
    if (isPlayer()) {
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

function enterEntrance(uuid: string): void {
    if (!entrance.includes(uuid)) {
        entrance.push(uuid);
    }

    render();
}

function leaveEntrance(uuid: string): void {
    const index = entrance.indexOf(uuid);
    if (index !== -1) {
        entrance.splice(index, 1);
    }

    render();
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
    }

    try {
        const response = await postOnce(`${apiUrl}/customer/${uuid}/seat`, formData);
        if (!response?.ok) return;

        if (toEntrance && !entrance.includes(uuid)) {
            entrance.push(uuid);
        }

        setFloor(response.data.floor);
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

    // A new customer has just walked in, so they start at the entrance
    entrance.push(response.uuid);
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

    return entrance.includes(customer.uuid) ? 'At the entrance' : 'Not here';
}

(<any>window).init                    = init;
(<any>window).renderSidebar           = renderSidebar;
(<any>window).switchTab               = switchTab;
(<any>window).addToPile               = addToPile;
(<any>window).queueConversion         = queueConversion;
(<any>window).saveSale                = saveSale;
(<any>window).removeSale              = removeSale;
(<any>window).toggleSidebar           = toggleSidebar;
(<any>window).openCreateCustomerModal = openCreateCustomerModal;
(<any>window).createCustomer          = createCustomer;
(<any>window).openModal               = openModal;
(<any>window).closeModal              = closeModal;
