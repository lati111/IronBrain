import {closeModal, openModal} from "../../../../components/modal";
import {submitForm} from "../shared";
import {cloneTemplate} from "../dom";
import {renderConversionRows, openConversionModal} from "./conversions";
import {describeConversion, findConversion, findRecipe, getDefinition, postInventory, setInventory, state} from "./inventory";
import {openEquipmentModal, openIngredientModal, renderTypeRows} from "./item-types";
import {openRecipeModal, renderRecipeRows} from "./recipes";
import {EDIT_MODALS, SidebarTab, TYPE_LABELS} from "./types";

/** The sidebar lists the ingredients, equipment, recipes and conversions, which are created, edited and deleted
 * through modals opened from it */

/** What the delete confirmation for ingredients, equipment, recipes and conversions deletes when confirmed */
let pendingTypeDelete: {type: SidebarTab, uuid: string} | null = null;

//| Rendering

export function renderSidebar(): void {
    const list = document.getElementById('inventory-list') as HTMLElement;
    const search = (document.getElementById('inventory-search') as HTMLInputElement).value.trim().toLowerCase();
    const tab = state.activeTab;

    list.replaceChildren();
    const total = tab === 'recipe'
        ? renderRecipeRows(list, search)
        : tab === 'conversion' ? renderConversionRows(list, search) : renderTypeRows(tab, list, search);

    if (list.childElementCount === 0) {
        const empty = document.createElement('li');
        empty.className = 'text-center text-gray-400 py-4 text-sm';
        empty.textContent = total === 0 ? `No ${TYPE_LABELS[tab].toLowerCase()} yet` : 'No results';
        list.appendChild(empty);
    }
}

export function createListRow(): HTMLElement {
    return cloneTemplate('list-row-template');
}

/** Hides action buttons of a sidebar row, such as "add" for what is never stored */
export function hideRowActions(row: HTMLElement, ...actions: string[]): void {
    for (const action of actions) {
        row.querySelector(`[data-action="${action}"]`)!.classList.add('hidden');
    }
}

export function switchTab(tab: SidebarTab): void {
    state.activeTab = tab;

    document.querySelectorAll('.inventory-tab[data-tab]').forEach((button) => {
        button.classList.toggle('active', (button as HTMLElement).dataset.tab === tab);
    });

    document.getElementById('inventory-create-type')!.textContent = `+ New ${TYPE_LABELS[tab]}`;
    renderSidebar();
}

//| Creating & editing

/** Opens the create modal of the active tab */
export function openCreateTypeModal(): void {
    const openers: Record<SidebarTab, (uuid: null) => void> = {
        ingredient: openIngredientModal,
        equipment:  openEquipmentModal,
        recipe:     openRecipeModal,
        conversion: openConversionModal,
    };
    openers[state.activeTab](null);
}

/**
 * Saves the form of a create / edit modal, either creating something new or saving what is open in it
 * @param fileFields File inputs that are left out when empty
 * @return The response data, or null when the form is invalid or saving failed
 */
export async function saveTypeForm(type: SidebarTab, form: HTMLFormElement, fileFields: string[] = []): Promise<any | null> {
    if (!form.reportValidity()) return null;

    const editing = state.editing[type];
    const url = editing !== null ? `${state.apiUrl}/${type}/${editing}/edit` : `${state.apiUrl}/${type}/create`;

    const response = await submitForm(form, url, fileFields);
    if (response === null) return null;

    state.editing[type] = null;
    closeModal(EDIT_MODALS[type]);
    setInventory(response.inventory);
    return response;
}

//| Deleting

export function openDeleteTypeModal(type: SidebarTab): void {
    const uuid = state.editing[type];
    if (uuid === null) return;

    const conversion = findConversion(uuid);
    const name = type === 'recipe'
        ? findRecipe(uuid)?.name
        : type === 'conversion'
            ? (conversion !== null ? describeConversion(conversion) : undefined)
            : getDefinition(type, uuid)?.name;

    pendingTypeDelete = {type, uuid};
    document.getElementById('type-delete-title')!.textContent = `Delete ${TYPE_LABELS[type].toLowerCase()}`;
    document.getElementById('type-delete-name')!.textContent = name ?? TYPE_LABELS[type];

    // The edit modal stays filled in, so cancelling can return to it as it was
    closeModal(EDIT_MODALS[type]);
    openModal('type-delete-modal');
}

export function cancelDeleteType(): void {
    closeModal('type-delete-modal');
    if (pendingTypeDelete !== null) {
        openModal(EDIT_MODALS[pendingTypeDelete.type]);
        pendingTypeDelete = null;
    }
}

export async function deleteType(): Promise<void> {
    if (pendingTypeDelete === null) return;

    const {type, uuid} = pendingTypeDelete;
    pendingTypeDelete = null;
    closeModal('type-delete-modal');

    // Also drops any unsaved general storage stacks of a deleted ingredient or equipment
    if (await postInventory(`${state.apiUrl}/${type}/${uuid}/delete`, new FormData(), true) !== null) {
        state.editing[type] = null;
    }
}
