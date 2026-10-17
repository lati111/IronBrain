import {openModal, closeModal} from "../../../../components/modal";
import {formatMoney, formatSatisfaction, refreshMoneyInputs, setupMoneyInput} from "../shared";
import {openOrderModal, OrderTable, SeatedCustomer} from "../orders";
import {createSpan, createTextElement, formatMinutes} from "./elements";
import {getServiceState, post} from "./state";
import {Order} from "./types";

/**
 * Looking after an order once it is placed: its portions being eaten, a tip or discount, and editing, finishing or
 * cancelling it
 */

/** The order open in the details modal, along with its table and who sits there, for editing it from there */
let details: {order: Order, table: OrderTable, customers: SeatedCustomer[]} | null = null;
let moneyInputsReady = false;

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
            const consume = createTextElement('button', 'interactive text-xs', 'Consumed');
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
        return `Being taken for ${formatMinutes(order.ordered_minutes_ago)}`;
    }
    if (order.status === 'taken') {
        return 'Taken, waiting to be noted down';
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

(<any>window).editOrder            = editOrder;
(<any>window).consumeOrder         = consumeOrder;
(<any>window).finishOrder          = finishOrder;
(<any>window).openCancelOrderModal = openCancelOrderModal;
(<any>window).keepOrder            = keepOrder;
(<any>window).cancelOrder          = cancelOrder;
