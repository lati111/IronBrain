import {closeModal, openModal} from "../../../../components/modal";
import {BoostTarget, MEAL_BOOST_TARGETS, submitForm} from "../shared";
import {setFormValues, setModalMode} from "../dom";
import {EditableTab, getTable, Player, postFloor, setFloor, state, TAB_LABELS} from "./floor";

/** The create / edit modals of tables, customers, employees and boosts, deleting them, and inviting players */

let pendingDelete: {type: EditableTab, uuid: string} | null = null;
let pendingPlayerRemove: Player | null = null;

//| Create / edit modals

/** Starts filling in the edit modal of a tab, for what is being edited or for something new, returning its form */
function prepareEditModal(type: EditableTab, uuid: string | null): HTMLFormElement {
    state.editing[type] = uuid;
    const form = document.getElementById(`${type}-form`) as HTMLFormElement;
    form.reset();
    return form;
}

function showEditModal(type: EditableTab, editing: boolean): void {
    setModalMode(type, TAB_LABELS[type], editing);
    openModal(`${type}-modal`);
}

/**
 * Saves the form of a tab's edit modal, either creating something new or saving what is open in it
 * @param fileFields File inputs that are left out when empty
 */
async function saveEditModal(type: EditableTab, fileFields: string[] = []): Promise<void> {
    const form = document.getElementById(`${type}-form`) as HTMLFormElement;
    if (!form.reportValidity()) return;

    const editing = state.editing[type];
    const url = editing !== null ? `${state.apiUrl}/${type}/${editing}/edit` : `${state.apiUrl}/${type}/create`;

    const response = await submitForm(form, url, fileFields);
    if (response === null) return;

    state.editing[type] = null;
    closeModal(`${type}-modal`);
    setFloor(response.floor);
}

//| Tables

export function openTableModal(uuid: string | null): void {
    const table = uuid !== null ? getTable(uuid) : null;
    if (uuid !== null && table === null) return;

    const form = prepareEditModal('table', uuid);
    if (table !== null) {
        setFormValues(form, {name: table.name, capacity: String(table.capacity)});
    }

    showEditModal('table', table !== null);
}

export async function saveTable(): Promise<void> {
    await saveEditModal('table');
}

//| Customers

export function openCustomerModal(uuid: string | null): void {
    const customer = uuid !== null ? state.floor.customers.find((candidate) => candidate.uuid === uuid) ?? null : null;
    if (uuid !== null && customer === null) return;

    const form = prepareEditModal('customer', uuid);
    if (customer !== null) {
        setFormValues(form, {name: customer.name, description: customer.description ?? ''});
    }

    showEditModal('customer', customer !== null);
}

export async function saveCustomer(): Promise<void> {
    await saveEditModal('customer');
}

//| Employees

export function openEmployeeModal(uuid: string | null): void {
    const employee = uuid !== null ? state.floor.employees.find((candidate) => candidate.uuid === uuid) ?? null : null;
    if (uuid !== null && employee === null) return;

    const form = prepareEditModal('employee', uuid);

    // The employee can be handed to one of the players to control during service
    const player = form.elements.namedItem('player_uuid') as HTMLSelectElement;
    player.replaceChildren(new Option('None', ''), ...state.floor.players.map((candidate) => new Option(candidate.username, candidate.uuid)));
    player.value = employee?.player_uuid ?? '';

    if (employee !== null) {
        setFormValues(form, {
            name:           employee.name,
            prep_speed:     String(employee.prep_speed),
            cleaning_speed: String(employee.cleaning_speed),
        });
    }

    showEditModal('employee', employee !== null);
}

export async function saveEmployee(): Promise<void> {
    // An empty icon field keeps the current icon
    await saveEditModal('employee', ['icon']);
}

//| Boosts

export function openBoostModal(uuid: string | null): void {
    const boost = uuid !== null ? state.floor.boosts.find((candidate) => candidate.uuid === uuid) ?? null : null;
    if (uuid !== null && boost === null) return;

    const form = prepareEditModal('boost', uuid);
    if (boost !== null) {
        setFormValues(form, {
            name:            boost.name,
            speed_bonus:     String(boost.speed_bonus),
            duration:        String(boost.duration),
            minutes_off:     String(boost.minutes_off),
            quality_bonus:   String(boost.quality_bonus),
            fresh_for_bonus: String(boost.fresh_for_bonus),
        });
        getBoostTargetInputs().forEach((input) => input.checked = boost.targets.includes(input.value as BoostTarget));
    }
    updateBoostFields();

    showEditModal('boost', boost !== null);
}

/** The checkboxes of what the boost in the boost modal can be activated on */
function getBoostTargetInputs(): HTMLInputElement[] {
    return [...document.querySelectorAll<HTMLInputElement>('#boost-form input[name="targets[]"]')];
}

/**
 * Only boosts that can be activated on a meal improve quality and freshness, so those fields only show for them. At
 * least one target has to be chosen, which the first checkbox reports when none is
 */
export function updateBoostFields(): void {
    const inputs = getBoostTargetInputs();
    const chosen = inputs.filter((input) => input.checked).map((input) => input.value as BoostTarget);
    document.getElementById('boost-meal-fields')!.classList.toggle('hidden', !chosen.some((target) => MEAL_BOOST_TARGETS.includes(target)));
    inputs[0].setCustomValidity(chosen.length === 0 ? 'Choose at least one thing it can be activated on' : '');
}

export async function saveBoost(): Promise<void> {
    updateBoostFields();
    await saveEditModal('boost');
}

//| Players

export function openInvitePlayerModal(): void {
    (document.getElementById('player-form') as HTMLFormElement).reset();
    openModal('player-modal');
}

export async function invitePlayer(): Promise<void> {
    const form = document.getElementById('player-form') as HTMLFormElement;
    if (!form.reportValidity()) return;

    if (await postFloor(`${state.apiUrl}/player/invite`, new FormData(form))) {
        closeModal('player-modal');
    }
}

export function openRemovePlayerModal(player: Player): void {
    pendingPlayerRemove = player;
    document.getElementById('player-remove-name')!.textContent = player.username;
    openModal('player-remove-modal');
}

export async function removePlayer(): Promise<void> {
    if (pendingPlayerRemove === null) return;

    const player = pendingPlayerRemove;
    pendingPlayerRemove = null;
    closeModal('player-remove-modal');
    await postFloor(`${state.apiUrl}/player/${player.uuid}/remove`);
}

//| Deleting tables, customers, employees & boosts

export function openDeleteModal(type: EditableTab): void {
    const uuid = state.editing[type];
    if (uuid === null) return;

    const name = {
        table: () => getTable(uuid)?.name,
        customer: () => state.floor.customers.find((customer) => customer.uuid === uuid)?.name,
        employee: () => state.floor.employees.find((employee) => employee.uuid === uuid)?.name,
        boost: () => state.floor.boosts.find((boost) => boost.uuid === uuid)?.name,
    }[type]();

    pendingDelete = {type, uuid};
    document.getElementById('type-delete-title')!.textContent = `Delete ${TAB_LABELS[type].toLowerCase()}`;
    document.getElementById('type-delete-name')!.textContent = name ?? TAB_LABELS[type];

    // The edit modal stays filled in, so cancelling can return to it as it was
    closeModal(`${type}-modal`);
    openModal('type-delete-modal');
}

export function cancelDelete(): void {
    closeModal('type-delete-modal');
    if (pendingDelete !== null) {
        openModal(`${pendingDelete.type}-modal`);
        pendingDelete = null;
    }
}

export async function confirmDelete(): Promise<void> {
    if (pendingDelete === null) return;

    const {type, uuid} = pendingDelete;
    pendingDelete = null;
    closeModal('type-delete-modal');

    if (await postFloor(`${state.apiUrl}/${type}/${uuid}/delete`)) {
        state.editing[type] = null;
    }
}
