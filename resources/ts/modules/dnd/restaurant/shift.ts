import {getData} from "../../../main";
import {openModal} from "../../../components/modal";
import {EXPIRED_STORAGE_KEY, formatMoney, postOnce} from "./shared";

/** Ending the service, after a look at what was sold and the profit made */

interface SoldItem {
    name: string;
    amount: number;
    total: number;
}

interface ServiceSummary {
    started_at: string;
    time: string;
    items: SoldItem[];
    profit: number;
}

function getRoot(): HTMLElement {
    return document.getElementById('service') as HTMLElement;
}

async function openEndServiceModal(): Promise<void> {
    const response = await getData(`${getRoot().dataset.serviceUrl}/summary`);
    if (!response?.ok) return;

    renderSummary(response.data);
    openModal('service-end-modal');
}

function renderSummary(summary: ServiceSummary): void {
    document.getElementById('service-end-hours')!.textContent = `${summary.started_at} – ${summary.time}`;
    document.getElementById('service-end-profit')!.textContent = formatMoney(summary.profit);

    const list = document.getElementById('service-end-items') as HTMLElement;
    list.replaceChildren();
    for (const item of summary.items) {
        const row = document.createElement('li');
        row.className = 'order-details-item';

        const name = document.createElement('span');
        name.className = 'flex-1 truncate';
        name.textContent = item.name;

        const amount = document.createElement('span');
        amount.className = 'text-gray-500';
        amount.textContent = `× ${item.amount}`;

        const total = document.createElement('span');
        total.className = 'min-w-16 text-right whitespace-nowrap';
        total.textContent = formatMoney(item.total);

        row.append(name, amount, total);
        list.appendChild(row);
    }

    if (summary.items.length === 0) {
        const empty = document.createElement('li');
        empty.className = 'text-gray-400 text-sm text-center py-2';
        empty.textContent = 'Nothing sold';
        list.appendChild(empty);
    }
}

async function endService(): Promise<void> {
    const formData = new FormData();
    const advanceDay = (document.getElementById('service-end-advance-day') as HTMLInputElement).checked;
    formData.append('advance_day', advanceDay ? '1' : '0');

    try {
        const response = await postOnce(`${getRoot().dataset.serviceUrl}/end`, formData);
        if (!response?.ok) return;

        if (response.data.expired.length > 0) {
            sessionStorage.setItem(EXPIRED_STORAGE_KEY, JSON.stringify(response.data.expired));
        }

        // The management page shows the message as a toast once it has loaded
        const url = new URL(getRoot().dataset.managementUrl!);
        url.searchParams.set('message', `Service ended, ${formatMoney(response.data.profit)} profit`);
        window.location.href = url.toString();
    } catch {
        // The error has already been shown to the user
    }
}

(<any>window).openEndServiceModal = openEndServiceModal;
(<any>window).endService          = endService;
