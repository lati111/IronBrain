import {openModal, closeModal} from "../../../components/modal";
import {formatMoney} from "./shared";
import {createEmptyRow, createSpan, pluralize} from "./kitchen/elements";
import {getServiceState, post} from "./kitchen/state";
import {KitchenRecipe, Order} from "./kitchen/types";

/**
 * Taking and editing orders at a table. Once placed, an order is looked after from its details, and paid into the
 * shift total once it is finished
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

    // A taken order is new as far as what was ordered goes, so it is placed rather than edited
    const editing = order !== null && order.status !== 'taken';
    document.getElementById('order-modal-title')!.textContent = editing ? `Edit order for ${table.name}` : `Order for ${table.name}`;
    document.getElementById('order-place')!.textContent = editing ? 'Save order' : 'Place order';
    (document.getElementById('order-recipe-search') as HTMLInputElement).value = '';

    renderOrderRecipes();
    renderOrderItems();
    openModal('order-modal');
    // Ready to type, as most orders start with looking something up
    (document.getElementById('order-recipe-search') as HTMLInputElement).focus();
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
    // A recipe is found by its name, its type or its category
    const matches = (recipe: KitchenRecipe) => [recipe.name, recipe.type, recipe.category ?? '']
        .some((text) => text.toLowerCase().includes(search));

    // The menu is split by type, such as meals and drinks, alphabetically. Variants share their base recipe's type
    const types = [...new Set(all.map((recipe) => recipe.type))].sort((a, b) => a.localeCompare(b));
    for (const type of types) {
        const section = all.filter((recipe) => recipe.parent_uuid === null && recipe.type === type);
        const rows = section.flatMap((base) => renderOrderRecipeGroup(base, all.filter((recipe) => recipe.parent_uuid === base.uuid), matches, search));
        if (rows.length === 0) continue;

        const header = document.createElement('li');
        header.className = 'order-recipe-type';
        header.append(createSpan(type, ''), createSpan(String(section.length), 'font-normal'));
        list.append(header, ...rows);
    }

    if (list.childElementCount === 0) {
        list.appendChild(createEmptyRow(all.length === 0 ? 'No recipes yet' : 'No results', 'py-2'));
    }
}

/**
 * Creates the rows of the menu for a recipe and its variants, as far as they match the search. A recipe with variants
 * is a collapsed group showing the range of their prices, which opens to list the recipe itself and each variant.
 * Searching for a variant opens its group
 * @returns The rows, none when nothing matches
 */
function renderOrderRecipeGroup(base: KitchenRecipe, variants: KitchenRecipe[], matches: (recipe: KitchenRecipe) => boolean, search: string): HTMLElement[] {
    if (variants.length === 0) {
        return matches(base) ? [createOrderRecipeRow(base)] : [];
    }

    // The whole group is shown when the base recipe matches, otherwise only the variants that do
    const shown = matches(base) ? [base, ...variants] : variants.filter(matches);
    if (shown.length === 0) return [];

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

    if (!expanded) return [header];

    return [header, ...shown.map((recipe) => {
        const row = createOrderRecipeRow(recipe, recipe === base ? `${base.name} (base)` : recipe.name);
        row.classList.add('order-recipe-variant');
        return row;
    })];
}

/** Enter adds the first recipe found to the order, and escape clears the search rather than closing the modal */
function handleOrderSearchKey(event: KeyboardEvent): void {
    const input = event.target as HTMLInputElement;
    if (event.key === 'Enter') {
        event.preventDefault();
        document.querySelector<HTMLElement>('#order-recipes [data-orderable]')?.click();
    } else if (event.key === 'Escape' && input.value !== '') {
        event.preventDefault();
        event.stopPropagation();
        input.value = '';
        renderOrderRecipes();
    }
}

/** Creates a row of the recipe list, which adds a portion of the recipe to the order when clicked */
function createOrderRecipeRow(recipe: KitchenRecipe, label: string = recipe.name): HTMLElement {
    const row = document.createElement('li');
    row.className = 'order-recipe';
    row.dataset.orderable = '';
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
        list.appendChild(createEmptyRow('No items yet', 'py-6'));
    }

    const total = draftItems.reduce((sum, item) => sum + item.price, 0);
    const portions = draftItems.length;
    document.getElementById('order-total')!.textContent = `${pluralize(portions, 'portion', 'portions')}, ${formatMoney(total)}`;
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

/** Whether the order modal is open, so a taken order isn't offered on top of it */
export function isOrderModalOpen(): boolean {
    return !document.getElementById('order-modal')!.classList.contains('hidden');
}

(<any>window).placeOrder           = placeOrder;
(<any>window).renderOrderRecipes   = renderOrderRecipes;
(<any>window).handleOrderSearchKey = handleOrderSearchKey;
