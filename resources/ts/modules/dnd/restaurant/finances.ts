import {getData} from "../../../main";
import {openModal, init as initModals, closeModal} from "../../../components/modal";
import {formatMoney, postOnce, refreshMoneyInputs, setField, setupMoneyInput} from "./shared";

interface Transaction {
    uuid: string;
    /** What the transaction was, such as the profit of a service or one added by hand */
    type: string;
    title: string;
    /** More about the transaction, shown when it is opened */
    description: string | null;
    /** In bits, negative when money went out */
    amount: number;
    /** In the restaurant's time, as Y-m-d H:i */
    occurred_at: string;
}

/** A day's finances: the profit of a service, broken down */
interface ServiceTransaction extends Transaction {
    service: {started_at: string, ended_at: string | null, profit: number};
    orders: FinanceOrder[];
    customers: {name: string, portions: number, profit: number}[];
    recipes: {name: string, amount: number, total: number}[];
}

interface FinanceOrder {
    uuid: string;
    table: string | null;
    status: string;
    /** As H:i */
    ordered_at: string;
    items: {name: string, price: number, customer: string | null}[];
    total_price: number;
    discount: number;
    tip: number;
    /** What was paid for it, nothing when cancelled */
    paid: number;
}

let apiUrl = '';

//| Initialisation

async function init(): Promise<void> {
    apiUrl = (document.getElementById('finances') as HTMLElement).dataset.apiUrl!;
    initModals();
    document.querySelectorAll<HTMLInputElement>('input[data-money]').forEach(setupMoneyInput);

    const response = await getData(apiUrl);
    renderTransactions(response.data.transactions as Transaction[]);
}

//| Transaction log

function renderTransactions(transactions: Transaction[]): void {
    const list = document.getElementById('finance-list') as HTMLElement;
    const template = document.getElementById('finance-row-template') as HTMLTemplateElement;
    list.replaceChildren();

    for (const transaction of transactions) {
        const row = template.content.firstElementChild!.cloneNode(true) as HTMLElement;
        setField(row, 'date', formatDateTime(transaction.occurred_at));
        setField(row, 'title', transaction.title);
        setAmount(row.querySelector('[data-field="amount"]') as HTMLElement, transaction.amount, true);

        row.addEventListener('click', () => openTransaction(transaction.uuid));
        row.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                openTransaction(transaction.uuid);
            }
        });

        list.appendChild(row);
    }

    if (transactions.length === 0) {
        const empty = document.createElement('li');
        empty.className = 'text-center text-gray-400 py-4 text-sm';
        empty.textContent = 'No transactions yet';
        list.appendChild(empty);
    }
}

//| Details

async function openTransaction(uuid: string): Promise<void> {
    const response = await getData(`${apiUrl}/${uuid}`);
    const transaction = response.data as Transaction;

    document.getElementById('finance-modal-title')!.textContent = transaction.title;
    const meta = document.getElementById('finance-modal-meta') as HTMLElement;
    meta.textContent = `${formatDateTime(transaction.occurred_at)} · `;
    const amount = document.createElement('span');
    setAmount(amount, transaction.amount, true);
    meta.appendChild(amount);

    const description = document.getElementById('finance-modal-description') as HTMLTextAreaElement;
    description.value = transaction.description ?? '';
    description.classList.toggle('hidden', transaction.description === null);

    const body = document.getElementById('finance-modal-body') as HTMLElement;
    body.replaceChildren();
    if ('service' in transaction) {
        renderServiceDetails(body, transaction as ServiceTransaction);
    }

    openModal('finance-modal');
}

//| Adding by hand

function openTransactionModal(): void {
    const form = document.getElementById('transaction-form') as HTMLFormElement;
    form.reset();
    refreshMoneyInputs(form);
    openModal('transaction-modal');
    (form.elements.namedItem('title') as HTMLInputElement).focus();
}

/** Adds the transaction, sending money going out as a negative amount */
async function createTransaction(): Promise<void> {
    const form = document.getElementById('transaction-form') as HTMLFormElement;
    if (!form.reportValidity()) return;

    const fields = new FormData(form);
    const amount = parseFloat(fields.get('amount') as string);
    const formData = new FormData();
    formData.append('title', fields.get('title') as string);
    formData.append('amount', String(fields.get('direction') === 'out' ? -amount : amount));
    formData.append('description', fields.get('description') as string);

    try {
        const response = await postOnce(`${apiUrl}/create`, formData);
        if (!response?.ok) return;
        response.announce();

        closeModal('transaction-modal');
        renderTransactions(response.data.transactions as Transaction[]);
    } catch {
        // The error has already been shown to the user
    }
}

/** Breaks a day's finances down into its orders, the profit per customer, and how many of each recipe were sold */
function renderServiceDetails(body: HTMLElement, transaction: ServiceTransaction): void {
    const service = transaction.service;
    const hours = document.createElement('p');
    hours.className = 'text-center text-sm';
    hours.textContent = `Open ${formatTime(service.started_at)} – ${service.ended_at !== null ? formatTime(service.ended_at) : 'now'}`;
    body.appendChild(hours);

    body.appendChild(createSection('Orders', ['Time', 'Table', 'Ordered', 'Price', 'Discount', 'Tip', 'Paid'],
        transaction.orders.map((order) => {
            const cancelled = order.status === 'cancelled';
            return {
                cells: [
                    order.ordered_at,
                    order.table ?? '—',
                    describeItems(order) + (cancelled ? ' (cancelled)' : ''),
                    formatMoney(order.total_price),
                    order.discount > 0 ? `-${formatMoney(order.discount)}` : '',
                    order.tip > 0 ? `+${formatMoney(order.tip)}` : '',
                    formatMoney(order.paid),
                ],
                muted: cancelled,
            };
        }),
        'No orders'));

    body.appendChild(createSection('Profit per customer', ['Customer', 'Portions', 'Profit'],
        transaction.customers.map((customer) => ({
            cells: [customer.name, String(customer.portions), formatMoney(customer.profit)],
        })),
        'Nothing sold'));

    body.appendChild(createSection('Recipes sold', ['Recipe', 'Sold', 'Worth'],
        transaction.recipes.map((recipe) => ({
            cells: [recipe.name, `×${recipe.amount}`, formatMoney(recipe.total)],
        })),
        'Nothing sold'));
}

/** Lists what was ordered, counting portions of the same recipe together, such as "2× Stew, Ale" */
function describeItems(order: FinanceOrder): string {
    const counts = new Map<string, number>();
    order.items.forEach((item) => counts.set(item.name, (counts.get(item.name) ?? 0) + 1));

    return [...counts].map(([name, amount]) => amount > 1 ? `${amount}× ${name}` : name).join(', ') || '—';
}

/** Creates a titled table, where the last column holds amounts and is aligned right */
function createSection(title: string, headers: string[], rows: {cells: string[], muted?: boolean}[], empty: string): HTMLElement {
    const section = document.createElement('section');
    const heading = document.createElement('h4');
    heading.className = 'finance-section-title';
    heading.textContent = title;
    section.appendChild(heading);

    if (rows.length === 0) {
        const note = document.createElement('p');
        note.className = 'text-gray-400 text-sm';
        note.textContent = empty;
        section.appendChild(note);
        return section;
    }

    const table = document.createElement('table');
    table.className = 'finance-table';
    const head = table.createTHead().insertRow();
    headers.forEach((header) => {
        const cell = document.createElement('th');
        cell.textContent = header;
        head.appendChild(cell);
    });

    const tbody = table.createTBody();
    for (const row of rows) {
        const tr = tbody.insertRow();
        tr.classList.toggle('muted', row.muted === true);
        row.cells.forEach((text) => tr.insertCell().textContent = text);
    }

    section.appendChild(table);
    return section;
}

//| Helpers

/** Shows an amount of money, coloured by whether it went in or out */
function setAmount(element: HTMLElement, amount: number, signed: boolean): void {
    element.textContent = signed && amount > 0 ? `+${formatMoney(amount)}` : formatMoney(amount);
    element.classList.add('finance-amount');
    element.classList.toggle('positive', amount > 0);
    element.classList.toggle('negative', amount < 0);
}

/** Formats a moment as Y-m-d H:i, as a readable date and time */
function formatDateTime(value: string): string {
    const date = new Date(value.replace(' ', 'T'));
    return `${date.toLocaleDateString(undefined, {day: 'numeric', month: 'long', year: 'numeric'})} ${formatTime(value)}`;
}

/** Takes the time out of a moment as Y-m-d H:i */
function formatTime(value: string): string {
    return value.slice(11, 16);
}

(<any>window).init                 = init;
(<any>window).openTransactionModal = openTransactionModal;
(<any>window).createTransaction    = createTransaction;
(<any>window).openModal            = openModal;
(<any>window).closeModal           = closeModal;
