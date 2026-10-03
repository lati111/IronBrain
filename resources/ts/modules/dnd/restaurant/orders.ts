import {openModal, closeModal} from "../../../components/modal";
import {formatMinutes, getServiceState, KitchenRecipe, Order, post} from "./kitchen";
import {formatMoney} from "./shared";

/** Taking orders at a table, and looking at the details of one */

export interface OrderTable {
    uuid: string;
    name: string;
}

export interface SeatedCustomer {
    uuid: string;
    name: string;
}

/** A single portion added to the order being taken, for one customer or, when null, for the table */
interface DraftItem {
    recipe: KitchenRecipe;
    customerUuid: string | null;
}

let orderTable: OrderTable | null = null;
let orderCustomers: SeatedCustomer[] = [];
let draftItems: DraftItem[] = [];
let detailsOrder: Order | null = null;

//| Taking an order

/**
 * Opens the order modal for a table. Recipes are picked from the list on the left, each adding a single portion to
 * the order on the right, where it is assigned to one of the customers seated at the table
 * @param table The table ordering
 * @param customers The customers seated at the table
 */
export function openOrderModal(table: OrderTable, customers: SeatedCustomer[]): void {
    orderTable = table;
    orderCustomers = customers;
    draftItems = [];

    document.getElementById('order-modal-title')!.textContent = `Order for ${table.name}`;
    (document.getElementById('order-recipe-search') as HTMLInputElement).value = '';

    renderOrderRecipes();
    renderOrderItems();
    openModal('order-modal');
}

/** Lists the recipes that can be added, filtered by the search */
function renderOrderRecipes(): void {
    const list = document.getElementById('order-recipes') as HTMLElement;
    const search = (document.getElementById('order-recipe-search') as HTMLInputElement).value.trim().toLowerCase();
    list.replaceChildren();

    const recipes = (getServiceState()?.recipes ?? []).filter((recipe) => recipe.name.toLowerCase().includes(search));
    for (const recipe of recipes) {
        const row = document.createElement('li');
        row.className = 'order-recipe';

        const name = document.createElement('span');
        name.className = 'flex-1 truncate';
        name.textContent = recipe.name;

        const price = document.createElement('span');
        price.className = 'text-xs text-gray-500 whitespace-nowrap';
        price.textContent = formatMoney(recipe.price);

        const add = document.createElement('span');
        add.className = 'interactive font-semibold';
        add.textContent = '+';

        row.append(name, price, add);
        row.addEventListener('click', () => {
            // Each click adds another portion, for the first customer without one yet, so a round is quick to take
            draftItems.push({recipe, customerUuid: suggestCustomer(recipe)});
            renderOrderItems();
        });
        list.appendChild(row);
    }

    if (list.childElementCount === 0) {
        const empty = document.createElement('li');
        empty.className = 'text-gray-400 text-sm text-center py-2';
        empty.textContent = (getServiceState()?.recipes ?? []).length === 0 ? 'No recipes yet' : 'No results';
        list.appendChild(empty);
    }
}

/** Suggests the first seated customer that hasn't been given this recipe yet, or the table when everyone has */
function suggestCustomer(recipe: KitchenRecipe): string | null {
    const given = new Set(draftItems.filter((item) => item.recipe.uuid === recipe.uuid).map((item) => item.customerUuid));
    return orderCustomers.find((customer) => !given.has(customer.uuid))?.uuid ?? null;
}

/** Lists the portions added to the order, each with the one customer it is for */
function renderOrderItems(): void {
    const list = document.getElementById('order-items') as HTMLElement;
    list.replaceChildren();

    draftItems.forEach((item, index) => {
        const row = document.createElement('li');
        row.className = 'order-item';

        const name = document.createElement('span');
        name.className = 'flex-1 truncate';
        name.textContent = item.recipe.name;

        const customer = document.createElement('select');
        customer.className = 'underlined text-sm w-32';
        customer.appendChild(new Option('Table', ''));
        for (const seated of orderCustomers) {
            customer.appendChild(new Option(seated.name, seated.uuid));
        }
        customer.value = item.customerUuid ?? '';
        customer.addEventListener('change', () => item.customerUuid = customer.value === '' ? null : customer.value);

        const price = document.createElement('span');
        price.className = 'min-w-14 text-right text-sm whitespace-nowrap';
        price.textContent = formatMoney(item.recipe.price);

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'px-1';
        remove.title = 'Remove from the order';
        remove.innerHTML = '&times;';
        remove.addEventListener('click', () => {
            draftItems.splice(index, 1);
            renderOrderItems();
        });

        row.append(name, customer, price, remove);
        list.appendChild(row);
    });

    if (draftItems.length === 0) {
        const empty = document.createElement('li');
        empty.className = 'text-gray-400 text-sm text-center py-6';
        empty.textContent = 'No items yet';
        list.appendChild(empty);
    }

    const total = draftItems.reduce((sum, item) => sum + item.recipe.price, 0);
    const portions = draftItems.length;
    document.getElementById('order-total')!.textContent = `${portions} ${portions === 1 ? 'portion' : 'portions'}, ${formatMoney(total)}`;
    (document.getElementById('order-place') as HTMLButtonElement).disabled = portions === 0;
}

async function placeOrder(): Promise<void> {
    if (orderTable === null || draftItems.length === 0) return;

    const items = draftItems.map((item) => ({recipe_uuid: item.recipe.uuid, customer_uuid: item.customerUuid}));

    // Sent as JSON, as the items are nested
    if (await post('/order', JSON.stringify({table_uuid: orderTable.uuid, items}))) {
        closeModal('order-modal');
    }
}

//| Order details

/**
 * Opens the details of an order, from where a fully served order can be finished once its meal is eaten
 * @param order The order
 * @param tableName The name of the table it was placed at
 */
export function openOrderDetails(order: Order, tableName: string): void {
    detailsOrder = order;

    document.getElementById('order-details-title')!.textContent = `${tableName}, ordered at ${order.ordered_at}`;
    document.getElementById('order-details-status')!.textContent = describeOrder(order);
    document.getElementById('order-details-total')!.textContent = formatMoney(order.total_price);

    const list = document.getElementById('order-details-items') as HTMLElement;
    list.replaceChildren();
    for (const item of order.items) {
        const row = document.createElement('li');
        row.className = 'order-details-item';
        row.classList.toggle('served', item.served);

        const name = document.createElement('span');
        name.className = 'flex-1 truncate';
        name.textContent = item.served ? `✓ ${item.name}` : item.name;

        const customer = document.createElement('span');
        customer.className = 'text-gray-500';
        customer.textContent = item.customer_name ?? 'Table';

        const price = document.createElement('span');
        price.className = 'min-w-16 text-right whitespace-nowrap';
        price.textContent = formatMoney(item.price);

        row.append(name, customer, price);
        list.appendChild(row);
    }

    document.getElementById('order-consume')!.classList.toggle('hidden', order.status !== 'fulfilled');
    openModal('order-details-modal');
}

/** Describes how far along an order is, with how long ago it was placed or fully served */
export function describeOrder(order: Order): string {
    const served = order.items.filter((item) => item.served).length;
    if (order.status === 'fulfilled') {
        return `All ${order.items.length} served ${formatMinutes(order.fulfilled_minutes_ago ?? 0)} ago`;
    }

    return `${served}/${order.items.length} served, ordered ${formatMinutes(order.ordered_minutes_ago)} ago`;
}

async function consumeOrder(): Promise<void> {
    if (detailsOrder === null) return;

    if (await post(`/order/${detailsOrder.uuid}/consume`)) {
        detailsOrder = null;
        closeModal('order-details-modal');
    }
}

(<any>window).placeOrder         = placeOrder;
(<any>window).renderOrderRecipes = renderOrderRecipes;
(<any>window).consumeOrder = consumeOrder;
