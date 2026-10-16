import {openModal, closeModal} from "../../../components/modal";
import {formatMinutes, getServiceState, KitchenRecipe, Order, post} from "./kitchen";
import {formatMoney, formatSatisfaction, refreshMoneyInputs, setupMoneyInput} from "./shared";

/**
 * Taking and editing orders at a table, and looking after one once it is placed: its portions being eaten, a tip or
 * discount, and finishing or cancelling it. An order is paid into the shift total once it is finished
 */

export interface OrderTable {
    uuid: string;
    name: string;
}

export interface SeatedCustomer {
    uuid: string;
    name: string;
}

/** A single portion on the order being taken or edited, for one customer or, when null, for the table */
interface DraftItem {
    /** The uuid of the item when it is already on the order, or null for a new one */
    uuid: string | null;
    /** The recipe of a new item. Existing items keep the recipe they were ordered as */
    recipeUuid: string | null;
    name: string;
    price: number;
    customerUuid: string | null;
    /** Served items can't be taken off the order */
    served: boolean;
}

let orderTable: OrderTable | null = null;
let orderCustomers: SeatedCustomer[] = [];
let draftItems: DraftItem[] = [];
/** The order being edited in the order modal, or null while taking a new one */
let editingOrder: Order | null = null;

/** The order open in the details modal, along with its table and who sits there, for editing it from there */
let details: {order: Order, table: OrderTable, customers: SeatedCustomer[]} | null = null;
let moneyInputsReady = false;

//| Taking & editing an order

/**
 * Opens the order modal for a table. Recipes are picked from the list on the left, each adding a single portion to
 * the order on the right, where it is assigned to one of the customers seated at the table
 * @param table The table ordering
 * @param customers The customers seated at the table
 * @param order The order to edit, or null to take a new one
 */
export function openOrderModal(table: OrderTable, customers: SeatedCustomer[], order: Order | null = null): void {
    orderTable = table;
    orderCustomers = customers;
    editingOrder = order;
    draftItems = (order?.items ?? []).map((item) => ({
        uuid: item.uuid,
        recipeUuid: item.recipe_uuid,
        name: item.name,
        price: item.price,
        customerUuid: item.customer_uuid,
        // A plate on its way can't be taken off the order either
        served: item.served || item.on_the_way,
    }));

    document.getElementById('order-modal-title')!.textContent = order !== null ? `Edit order for ${table.name}` : `Order for ${table.name}`;
    document.getElementById('order-place')!.textContent = order !== null ? 'Save order' : 'Place order';
    (document.getElementById('order-recipe-search') as HTMLInputElement).value = '';

    renderOrderRecipes();
    renderOrderItems();
    openModal('order-modal');
}

/** The recipes with variants opened in the recipe list, by uuid. Collapsed by default */
const expandedRecipes = new Set<string>();

/**
 * Lists the recipes that can be added, filtered by the search. A recipe with variants is a collapsed group showing
 * the range of their prices, which opens to list the recipe itself and each variant. Searching for a variant opens
 * its group
 */
function renderOrderRecipes(): void {
    const list = document.getElementById('order-recipes') as HTMLElement;
    const search = (document.getElementById('order-recipe-search') as HTMLInputElement).value.trim().toLowerCase();
    list.replaceChildren();

    const all = getServiceState()?.recipes ?? [];
    const matches = (recipe: KitchenRecipe) => recipe.name.toLowerCase().includes(search);
    for (const base of all.filter((recipe) => recipe.parent_uuid === null)) {
        const variants = all.filter((recipe) => recipe.parent_uuid === base.uuid);
        if (variants.length === 0) {
            if (matches(base)) list.appendChild(createOrderRecipeRow(base));
            continue;
        }

        // The whole group is shown when the base recipe matches, otherwise only the variants that do
        const shown = matches(base) ? [base, ...variants] : variants.filter(matches);
        if (shown.length === 0) continue;

        const expanded = expandedRecipes.has(base.uuid) || (search !== '' && !matches(base));
        const prices = [base, ...variants].map((recipe) => recipe.price);
        const low = Math.min(...prices);
        const high = Math.max(...prices);

        const header = document.createElement('li');
        header.className = 'order-recipe order-recipe-group';
        header.classList.toggle('expanded', expanded);
        header.append(
            createSpan(expanded ? '▾' : '▸', 'text-xs text-gray-500 w-3'),
            createSpan(base.name, 'flex-1 truncate'),
            createSpan(low === high ? formatMoney(low) : `${formatMoney(low)} – ${formatMoney(high)}`, 'text-xs text-gray-500 whitespace-nowrap'),
        );
        header.addEventListener('click', () => {
            if (!expandedRecipes.delete(base.uuid)) expandedRecipes.add(base.uuid);
            renderOrderRecipes();
        });
        list.appendChild(header);

        if (expanded) {
            for (const recipe of shown) {
                const row = createOrderRecipeRow(recipe, recipe === base ? `${base.name} (base)` : recipe.name);
                row.classList.add('order-recipe-variant');
                list.appendChild(row);
            }
        }
    }

    if (list.childElementCount === 0) {
        appendEmptyRow(list, all.length === 0 ? 'No recipes yet' : 'No results', 'py-2');
    }
}

/** Creates a row of the recipe list, which adds a portion of the recipe to the order when clicked */
function createOrderRecipeRow(recipe: KitchenRecipe, label: string = recipe.name): HTMLElement {
    const row = document.createElement('li');
    row.className = 'order-recipe';
    row.append(
        createSpan(label, 'flex-1 truncate'),
        createSpan(formatMoney(recipe.price), 'text-xs text-gray-500 whitespace-nowrap'),
        createSpan('+', 'interactive font-semibold'),
    );

    row.addEventListener('click', () => {
        // Each click adds another portion, for the first customer without one yet, so a round is quick to take
        draftItems.push({
            uuid: null,
            recipeUuid: recipe.uuid,
            name: recipe.name,
            price: recipe.price,
            customerUuid: suggestCustomer(recipe),
            served: false,
        });
        renderOrderItems();
    });

    return row;
}

/** Suggests the first seated customer that hasn't been given this recipe yet, or the table when everyone has */
function suggestCustomer(recipe: KitchenRecipe): string | null {
    const given = new Set(draftItems.filter((item) => item.recipeUuid === recipe.uuid).map((item) => item.customerUuid));
    return orderCustomers.find((customer) => !given.has(customer.uuid))?.uuid ?? null;
}

/** Lists the portions on the order, each with the one customer it is for */
function renderOrderItems(): void {
    const list = document.getElementById('order-items') as HTMLElement;
    list.replaceChildren();

    draftItems.forEach((item, index) => {
        const row = document.createElement('li');
        row.className = 'order-item';
        row.classList.toggle('served', item.served);

        const customer = document.createElement('select');
        customer.className = 'underlined text-sm w-32';
        customer.appendChild(new Option('Table', ''));
        for (const seated of orderCustomers) {
            customer.appendChild(new Option(seated.name, seated.uuid));
        }
        customer.value = item.customerUuid ?? '';
        customer.addEventListener('change', () => item.customerUuid = customer.value === '' ? null : customer.value);

        // What has been brought to the table stays on the order
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'px-1';
        remove.title = 'Remove from the order';
        remove.innerHTML = '&times;';
        remove.classList.toggle('invisible', item.served);
        remove.addEventListener('click', () => {
            draftItems.splice(index, 1);
            renderOrderItems();
        });

        row.append(
            createSpan(item.served ? `✓ ${item.name}` : item.name, 'flex-1 truncate'),
            customer,
            createSpan(formatMoney(item.price), 'min-w-14 text-right text-sm whitespace-nowrap'),
            remove,
        );
        list.appendChild(row);
    });

    if (draftItems.length === 0) {
        appendEmptyRow(list, 'No items yet', 'py-6');
    }

    const total = draftItems.reduce((sum, item) => sum + item.price, 0);
    const portions = draftItems.length;
    document.getElementById('order-total')!.textContent = `${portions} ${portions === 1 ? 'portion' : 'portions'}, ${formatMoney(total)}`;
    (document.getElementById('order-place') as HTMLButtonElement).disabled = portions === 0;
}

/** Places the order being taken, or saves the one being edited */
async function placeOrder(): Promise<void> {
    if (orderTable === null || draftItems.length === 0) return;

    // Sent as JSON, as the items are nested. Existing items are sent by their uuid, new ones by their recipe
    const items = draftItems.map((item) => item.uuid !== null
        ? {uuid: item.uuid, customer_uuid: item.customerUuid}
        : {recipe_uuid: item.recipeUuid, customer_uuid: item.customerUuid});

    const saved = editingOrder !== null
        ? await post(`/order/${editingOrder.uuid}/edit`, JSON.stringify({items}))
        : await post('/order', JSON.stringify({table_uuid: orderTable.uuid, items}));

    if (saved) {
        editingOrder = null;
        closeModal('order-modal');
    }
}

//| Order details

/**
 * Opens the details of an order, from where its portions are marked as eaten, a tip or discount is given, and the
 * order is edited, finished or cancelled
 * @param order The order
 * @param table The table it was placed at
 * @param customers The customers seated at the table, for editing the order
 */
export function openOrderDetails(order: Order, table: OrderTable, customers: SeatedCustomer[]): void {
    details = {order, table, customers};
    setupDetailMoneyInputs();

    (document.getElementById('order-tip') as HTMLInputElement).value = String(order.tip);
    (document.getElementById('order-discount') as HTMLInputElement).value = String(order.discount);
    refreshMoneyInputs(document.getElementById('order-details-modal')!);

    renderOrderDetails();
    openModal('order-details-modal');
}

/** Fills the details modal with the order as it currently is */
function renderOrderDetails(): void {
    if (details === null) return;
    const {order, table} = details;

    document.getElementById('order-details-title')!.textContent = `${table.name}, ordered at ${order.ordered_at}`;
    const status = describeOrder(order);
    document.getElementById('order-details-status')!.textContent = order.satisfaction !== null
        ? `${status} · ${formatSatisfaction(order.satisfaction)}`
        : status;

    const list = document.getElementById('order-details-items') as HTMLElement;
    list.replaceChildren();
    for (const item of order.items) {
        const row = document.createElement('li');
        row.className = 'order-details-item';
        row.classList.toggle('served', item.served);
        row.classList.toggle('consumed', item.consumed);

        const actions = document.createElement('span');
        actions.className = 'w-20 text-right';
        if (item.served && !item.consumed) {
            const consume = document.createElement('button');
            consume.className = 'interactive text-xs';
            consume.textContent = 'Consumed';
            consume.addEventListener('click', () => orderAction(`/item/${item.uuid}/consume`));
            actions.appendChild(consume);
        }

        const name = item.served ? `✓ ${item.name}` : item.on_the_way ? `${item.name} (on its way)` : item.name;
        const satisfaction = createSpan(item.satisfaction !== null ? formatSatisfaction(item.satisfaction) : '', 'order-satisfaction w-10 text-right');
        satisfaction.title = 'Satisfaction';
        row.append(
            createSpan(name, 'flex-1 truncate'),
            satisfaction,
            createSpan(item.customer_name ?? 'Table', 'text-gray-500'),
            createSpan(formatMoney(item.price), 'min-w-16 text-right whitespace-nowrap'),
            actions,
        );
        list.appendChild(row);
    }

    document.getElementById('order-details-subtotal')!.textContent = formatMoney(order.total_price);
    document.getElementById('order-details-total')!.textContent = formatMoney(order.payable);

    const uneaten = order.items.some((item) => item.served && !item.consumed);
    document.getElementById('order-consume')!.classList.toggle('hidden', !uneaten);
}

/** Describes how far along an order is, with how long ago it was placed or fully served */
export function describeOrder(order: Order): string {
    if (order.status === 'taking') {
        return `Being taken, ordered ${formatMinutes(order.ordered_minutes_ago)} ago`;
    }

    const served = order.items.filter((item) => item.served).length;
    if (order.status === 'fulfilled') {
        return `All ${order.items.length} served ${formatMinutes(order.fulfilled_minutes_ago ?? 0)} ago`;
    }

    return `${served}/${order.items.length} served, ordered ${formatMinutes(order.ordered_minutes_ago)} ago`;
}

/** The tip and discount fields show coins while not being edited, set up the first time the details open */
function setupDetailMoneyInputs(): void {
    if (moneyInputsReady) return;
    moneyInputsReady = true;

    for (const id of ['order-tip', 'order-discount']) {
        const input = document.getElementById(id) as HTMLInputElement;
        setupMoneyInput(input);
        input.addEventListener('change', saveTipAndDiscount);
    }
}

async function saveTipAndDiscount(): Promise<void> {
    if (details === null) return;

    const tip = document.getElementById('order-tip') as HTMLInputElement;
    const discount = document.getElementById('order-discount') as HTMLInputElement;
    if (!tip.reportValidity() || !discount.reportValidity()) return;

    const formData = new FormData();
    formData.append('tip', tip.value === '' ? '0' : tip.value);
    formData.append('discount', discount.value === '' ? '0' : discount.value);
    await orderAction('/adjust', formData, false);
}

function editOrder(): void {
    if (details === null) return;

    const {order, table, customers} = details;
    closeModal('order-details-modal');
    openOrderModal(table, customers, order);
}

function consumeOrder(): void {
    orderAction('/consume');
}

function finishOrder(): void {
    orderAction('/finish');
}

function openCancelOrderModal(): void {
    if (details === null) return;

    closeModal('order-details-modal');
    openModal('order-cancel-modal');
}

/** Goes back to the details of the order, as cancelling it was called off */
function keepOrder(): void {
    closeModal('order-cancel-modal');
    if (details !== null) openModal('order-details-modal');
}

async function cancelOrder(): Promise<void> {
    closeModal('order-cancel-modal');
    await orderAction('/cancel');
}

/**
 * Performs an action on the order open in the details modal, then shows it as it is now, or closes the details once
 * the order is done
 * @param path The path of the action, after the order's own path
 * @param formData The parameters of the action
 * @param announce Whether to show the result as a toast
 */
async function orderAction(path: string, formData: FormData = new FormData(), announce: boolean = true): Promise<void> {
    if (details === null) return;

    if (!await post(`/order/${details.order.uuid}${path}`, formData, announce)) return;

    const order = getServiceState()?.orders.find((candidate) => candidate.uuid === details?.order.uuid) ?? null;
    if (order === null) {
        details = null;
        closeModal('order-details-modal');
        return;
    }

    details.order = order;
    renderOrderDetails();
}

//| Helpers

function createSpan(text: string, className: string): HTMLElement {
    const span = document.createElement('span');
    span.className = className;
    span.textContent = text;
    return span;
}

function appendEmptyRow(list: HTMLElement, text: string, padding: string): void {
    const empty = document.createElement('li');
    empty.className = `text-gray-400 text-sm text-center ${padding}`;
    empty.textContent = text;
    list.appendChild(empty);
}

(<any>window).placeOrder           = placeOrder;
(<any>window).renderOrderRecipes   = renderOrderRecipes;
(<any>window).editOrder            = editOrder;
(<any>window).consumeOrder         = consumeOrder;
(<any>window).finishOrder          = finishOrder;
(<any>window).openCancelOrderModal = openCancelOrderModal;
(<any>window).keepOrder            = keepOrder;
(<any>window).cancelOrder          = cancelOrder;
