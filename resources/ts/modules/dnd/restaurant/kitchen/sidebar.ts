import {openModal, closeModal} from "../../../../components/modal";
import {describeMealBoostEffects, describeSpeedBonus, formatMoney, refreshMoneyInputs, setField, setupMoneyInput} from "../shared";
import {setupBoostDrag} from "./drag";
import {cloneTemplate, createEmptyRow, formatMinutes} from "./elements";
import {getServiceState, post} from "./state";
import {KitchenRecipe, ServiceBoost, ServiceConversion, ServiceIngredient} from "./types";

/**
 * The sidebar tabs listing what comes with the service state: the ingredients that can be brought in during service,
 * the conversions that can be done in the prep area, the boosts, and the recipes' sales
 */

export type KitchenTab = 'ingredient' | 'conversion' | 'boost' | 'sale';

/** The ingredient the pile modal is adding */
let pileIngredient: ServiceIngredient | null = null;

/** The conversion the conversion modal queues */
let pendingConversion: ServiceConversion | null = null;

/** The recipe the sale modal is for */
let saleRecipe: KitchenRecipe | null = null;

export function initSidebar(): void {
    setupMoneyInput(document.getElementById('sale-price') as HTMLInputElement);
}

/**
 * Fills the sidebar list with a row for each item whose name matches the search, or a note when there are none
 * @param items The items to list
 * @param getName The name the item is listed and searched by
 * @param emptyText What the note says when there are no items at all
 * @param fill Fills the rest of an item's row
 */
export function renderSidebarList<T>(items: T[], getName: (item: T) => string, emptyText: string, fill: (row: HTMLElement, item: T) => void): void {
    const list = document.getElementById('inventory-list') as HTMLElement;
    const search = (document.getElementById('inventory-search') as HTMLInputElement).value.trim().toLowerCase();

    list.replaceChildren();
    for (const item of items) {
        const name = getName(item);
        if (!name.toLowerCase().includes(search)) continue;

        const row = cloneTemplate('list-row-template');
        setField(row, 'name', name);
        fill(row, item);
        list.appendChild(row);
    }

    if (list.childElementCount === 0) {
        list.appendChild(createEmptyRow(items.length === 0 ? emptyText : 'No results', 'py-4'));
    }
}

export function getAddButton(row: HTMLElement): HTMLElement {
    return row.querySelector('[data-action="add"]') as HTMLElement;
}

export function renderKitchenTab(tab: KitchenTab): void {
    const state = getServiceState();
    switch (tab) {
        case 'ingredient':
            renderSidebarList(state?.ingredients ?? [], (ingredient) => ingredient.name, 'No ingredients yet', fillIngredientRow);
            break;
        case 'conversion':
            renderSidebarList(state?.conversions ?? [], describeConversion, 'No conversions yet', fillConversionRow);
            break;
        case 'boost':
            renderSidebarList(state?.boosts ?? [], (boost) => boost.name, 'No boosts yet', fillBoostRow);
            break;
        case 'sale':
            renderSidebarList(state?.recipes ?? [], (recipe) => recipe.name, 'No recipes yet', fillSaleRow);
            break;
    }
}

//| Ingredients

/** Each ingredient can be added to the pile of ingredients brought in during service */
function fillIngredientRow(row: HTMLElement, ingredient: ServiceIngredient): void {
    setField(row, 'meta', ingredient.pile > 0
        ? `${ingredient.stored} stored · ${ingredient.pile} unsorted`
        : `${ingredient.stored} stored`);

    const addButton = getAddButton(row);
    addButton.title = 'Add';
    addButton.addEventListener('click', () => openPileModal(ingredient));
}

function openPileModal(ingredient: ServiceIngredient): void {
    pileIngredient = ingredient;
    document.getElementById('pile-modal-title')!.textContent = `Add ${ingredient.name}`;

    const input = document.getElementById('pile-amount') as HTMLInputElement;
    input.value = '1';
    openModal('pile-modal');
    input.focus();
    input.select();
}

async function addToPile(): Promise<void> {
    const form = document.getElementById('pile-form') as HTMLFormElement;
    if (pileIngredient === null || !form.reportValidity()) return;

    if (await post(`/ingredient/${pileIngredient.uuid}/add`, new FormData(form))) {
        pileIngredient = null;
        closeModal('pile-modal');
    }
}

//| Conversions

function describeConversion(conversion: ServiceConversion): string {
    return `${conversion.input_name} → ${conversion.output_amount} ${conversion.output_name}`;
}

/** Each conversion can be queued in the prep area */
function fillConversionRow(row: HTMLElement, conversion: ServiceConversion): void {
    setField(row, 'meta', `${conversion.available} stored · ${formatMinutes(conversion.minutes)} each`);

    const addButton = getAddButton(row);
    addButton.title = 'Convert';
    addButton.addEventListener('click', () => openConversionModal(conversion));
}

function openConversionModal(conversion: ServiceConversion): void {
    pendingConversion = conversion;
    document.getElementById('conversion-modal-title')!.textContent = `Convert ${conversion.input_name} into ${conversion.output_name}`;

    const input = document.getElementById('conversion-amount') as HTMLInputElement;
    input.value = '1';
    input.max = conversion.available > 0 ? String(conversion.available) : '';
    openModal('conversion-modal');
    input.focus();
    input.select();
}

async function queueConversion(): Promise<void> {
    const form = document.getElementById('conversion-form') as HTMLFormElement;
    if (pendingConversion === null || !form.reportValidity()) return;

    if (await post(`/conversion/${pendingConversion.uuid}/queue`, new FormData(form))) {
        pendingConversion = null;
        closeModal('conversion-modal');
    }
}

//| Boosts

/** Each boost is dragged onto what it boosts, as often as wanted */
function fillBoostRow(row: HTMLElement, boost: ServiceBoost): void {
    setField(row, 'meta', describeBoostEffects(boost));
    getAddButton(row).classList.add('hidden');

    row.classList.add('draggable');
    setupBoostDrag(row, boost);
}

/** Describes what a boost does, such as "+10% speed for 30m · 5m off" */
function describeBoostEffects(boost: ServiceBoost): string {
    const effects: string[] = [];
    if (boost.speed_bonus > 0) effects.push(`${describeSpeedBonus(boost.speed_bonus)} for ${formatMinutes(boost.duration)}`);
    if (boost.minutes_off > 0) effects.push(`${formatMinutes(boost.minutes_off)} off`);
    effects.push(...describeMealBoostEffects(boost));
    return effects.length > 0 ? effects.join(' · ') : 'No effect';
}

//| Sales

/** Each recipe shows its price, and its sale price for this service when it is on sale */
function fillSaleRow(row: HTMLElement, recipe: KitchenRecipe): void {
    row.classList.add('clickable');
    row.classList.toggle('on-sale', recipe.on_sale);
    setField(row, 'meta', recipe.on_sale
        ? `${formatMoney(recipe.base_price)} → ${formatMoney(recipe.price)}`
        : formatMoney(recipe.base_price));

    // The whole row opens the sale, so the add button isn't needed
    getAddButton(row).classList.add('hidden');
    row.addEventListener('click', () => openSaleModal(recipe));
}

function openSaleModal(recipe: KitchenRecipe): void {
    saleRecipe = recipe;
    document.getElementById('sale-modal-title')!.textContent = `${recipe.name} on sale`;
    document.getElementById('sale-remove')!.classList.toggle('hidden', !recipe.on_sale);

    const input = document.getElementById('sale-price') as HTMLInputElement;
    input.value = String(recipe.price);
    refreshMoneyInputs(input.form!);
    openModal('sale-modal');
}

async function saveSale(): Promise<void> {
    const form = document.getElementById('sale-form') as HTMLFormElement;
    if (saleRecipe === null || !form.reportValidity()) return;

    if (await post(`/sale/${saleRecipe.uuid}`, new FormData(form))) {
        saleRecipe = null;
        closeModal('sale-modal');
    }
}

async function removeSale(): Promise<void> {
    if (saleRecipe === null) return;

    if (await post(`/sale/${saleRecipe.uuid}/remove`)) {
        saleRecipe = null;
        closeModal('sale-modal');
    }
}

(<any>window).addToPile       = addToPile;
(<any>window).queueConversion = queueConversion;
(<any>window).saveSale        = saveSale;
(<any>window).removeSale      = removeSale;
