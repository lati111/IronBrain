import {openModal} from "../../../../components/modal";
import {postInventory, state} from "./inventory";
import {ExpiredStack} from "./types";

/** The restaurant's date, which ages the stored ingredients as it moves on */

export function renderDate(): void {
    if (state.inventory.date === '') return;

    const date = new Date(`${state.inventory.date}T00:00:00`);
    document.getElementById('inventory-date')!.textContent = date.toLocaleDateString(undefined, {day: 'numeric', month: 'long', year: 'numeric'});
}

/** Moves the date a day forward, which ages the stored ingredients, and shows any that expired */
export async function advanceDay(): Promise<void> {
    const data = await postInventory(`${state.apiUrl}/advance-day`);
    if (data !== null) {
        showExpired(data.expired as ExpiredStack[]);
    }
}

export function showExpired(expired: ExpiredStack[]): void {
    if (expired.length === 0) return;

    const list = document.getElementById('expired-items') as HTMLElement;
    list.replaceChildren();
    for (const stack of expired) {
        const row = document.createElement('li');
        row.className = 'order-details-item';

        const name = document.createElement('span');
        name.className = 'flex-1 truncate';
        name.textContent = `${stack.name} ×${stack.amount}`;

        const location = document.createElement('span');
        location.className = 'text-gray-500';
        location.textContent = stack.location;

        row.append(name, location);
        list.appendChild(row);
    }

    openModal('expired-modal');
}
