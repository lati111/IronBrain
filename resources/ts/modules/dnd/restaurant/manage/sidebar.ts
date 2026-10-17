import {setField} from "../shared";
import {cloneTemplate} from "../dom";
import {BoostType, getRoom, getTable, seatsLabel, SidebarTab, state, TAB_LABELS} from "./floor";
import {
    openBoostModal, openCustomerModal, openEmployeeModal, openInvitePlayerModal, openRemovePlayerModal, openTableModal,
} from "./modals";
import {setupTableDrag} from "./rooms";

/** The sidebar lists the tables, customers, employees, players and boosts of the restaurant */

/** What each kind of boost is activated on, as shown */
const BOOST_TARGET_LABELS: Record<BoostType['target'], string> = {
    equipment: 'Equipment',
    meal:      'Meal',
    area:      'Kitchen area',
};

export function renderSidebar(): void {
    const list = document.getElementById('inventory-list') as HTMLElement;
    const search = (document.getElementById('inventory-search') as HTMLInputElement).value.trim().toLowerCase();

    list.replaceChildren();
    const total = {
        table: renderTableRows,
        customer: renderCustomerRows,
        employee: renderEmployeeRows,
        player: renderPlayerRows,
        boost: renderBoostRows,
    }[state.activeTab](list, search);

    if (list.childElementCount === 0) {
        const empty = document.createElement('li');
        empty.className = 'text-center text-gray-400 py-4 text-sm';
        empty.textContent = total === 0 ? `No ${TAB_LABELS[state.activeTab].toLowerCase()}s yet` : 'No results';
        list.appendChild(empty);
    }
}

/**
 * Creates a sidebar row with a name and a line about it
 * @param onEdit Called when the row's edit button is clicked
 */
function createListRow(name: string, meta: string, onEdit: () => void): HTMLElement {
    const row = cloneTemplate('list-row-template');
    setField(row, 'name', name);
    setField(row, 'meta', meta);
    row.querySelector('[data-action="edit"]')!.addEventListener('click', onEdit);
    return row;
}

/** Renders the sidebar rows for tables, returning how many tables exist before filtering */
function renderTableRows(list: HTMLElement, search: string): number {
    for (const table of state.floor.tables.filter((table) => table.name.toLowerCase().includes(search))) {
        const row = createListRow(table.name, `${seatsLabel(table.capacity)} · ${getRoom(table.room_uuid)?.name ?? 'Not placed'}`,
            () => openTableModal(table.uuid));

        // Tables are placed in rooms by dragging them from the sidebar
        row.draggable = true;
        setupTableDrag(row, table);

        list.appendChild(row);
    }

    return state.floor.tables.length;
}

/** Renders the sidebar rows for customers, returning how many customers exist before filtering */
function renderCustomerRows(list: HTMLElement, search: string): number {
    for (const customer of state.floor.customers.filter((customer) => customer.name.toLowerCase().includes(search))) {
        const seated = customer.table_uuid !== null
            ? `Seated at ${getTable(customer.table_uuid)?.name ?? 'a table'}, seat ${customer.seat}`
            : 'Not seated';
        const row = createListRow(customer.name, seated, () => openCustomerModal(customer.uuid));
        row.title = customer.description ?? '';

        list.appendChild(row);
    }

    return state.floor.customers.length;
}

/** Renders the sidebar rows for employees, returning how many employees exist before filtering */
function renderEmployeeRows(list: HTMLElement, search: string): number {
    for (const employee of state.floor.employees.filter((employee) => employee.name.toLowerCase().includes(search))) {
        const meta = `Prep ×${employee.prep_speed} · Cleaning ×${employee.cleaning_speed}`;
        list.appendChild(createListRow(employee.name, employee.player_name !== null ? `${meta} · ${employee.player_name}` : meta,
            () => openEmployeeModal(employee.uuid)));
    }

    return state.floor.employees.length;
}

/** Renders the sidebar rows for players, returning how many players there are before filtering */
function renderPlayerRows(list: HTMLElement, search: string): number {
    for (const player of state.floor.players.filter((player) => player.username.toLowerCase().includes(search))) {
        const employees = state.floor.employees.filter((employee) => employee.player_uuid === player.uuid).map((employee) => employee.name);
        const row = createListRow(player.username, employees.length > 0 ? employees.join(', ') : 'No employees',
            () => openRemovePlayerModal(player));

        // Players are invited rather than edited, so their button removes them
        const button = row.querySelector('[data-action="edit"]') as HTMLElement;
        button.innerHTML = '&times;';
        button.title = 'Remove';

        list.appendChild(row);
    }

    return state.floor.players.length;
}

/** Renders the sidebar rows for boosts, returning how many boosts exist before filtering */
function renderBoostRows(list: HTMLElement, search: string): number {
    for (const boost of state.floor.boosts.filter((boost) => boost.name.toLowerCase().includes(search))) {
        const row = createListRow(boost.name, describeBoost(boost), () => openBoostModal(boost.uuid));
        row.title = describeBoost(boost);

        list.appendChild(row);
    }

    return state.floor.boosts.length;
}

/** Describes what a boost is for and does, such as "Equipment · +0.5 speed for 30 min, 5 min off" */
function describeBoost(boost: BoostType): string {
    const effects: string[] = [];
    if (boost.speed_bonus > 0) effects.push(`+${boost.speed_bonus} speed for ${boost.duration} min`);
    if (boost.minutes_off > 0) effects.push(`${boost.minutes_off} min off`);
    if (boost.quality_bonus !== 0) effects.push(`${boost.quality_bonus > 0 ? '+' : ''}${boost.quality_bonus} quality`);

    return `${BOOST_TARGET_LABELS[boost.target]} · ${effects.length > 0 ? effects.join(', ') : 'no effect'}`;
}

export function switchTab(tab: SidebarTab): void {
    state.activeTab = tab;

    document.querySelectorAll<HTMLElement>('.inventory-tab[data-tab]').forEach((button) => {
        button.classList.toggle('active', button.dataset.tab === tab);
    });

    document.getElementById('inventory-create-type')!.textContent = tab === 'player' ? '+ Invite Player' : `+ New ${TAB_LABELS[tab]}`;
    renderSidebar();
}

/** Opens the create modal of the active tab, or invites a player on the players tab */
export function openCreateModal(): void {
    const openers: Record<SidebarTab, () => void> = {
        table:    () => openTableModal(null),
        customer: () => openCustomerModal(null),
        employee: () => openEmployeeModal(null),
        player:   openInvitePlayerModal,
        boost:    () => openBoostModal(null),
    };
    openers[state.activeTab]();
}
