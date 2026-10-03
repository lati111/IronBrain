import {getData} from "../../../main";
import {openModal, closeModal} from "../../../components/modal";
import {formatMoney, postOnce, setField, setupDropTarget} from "./shared";

/** The service clock and the kitchen of the service page */

type MealStatus = 'planned' | 'prepped' | 'cooking' | 'done' | 'serving';

interface Clock {
    date: string;
    time: string;
    opening_time: string;
    closing_time: string;
    /** What the orders placed during the current service are worth */
    shift_total: number;
}

export interface KitchenRecipe {
    uuid: string;
    name: string;
    price: number;
    prep_time: number;
    cooking_time: number;
}

interface Cooker {
    uuid: string;
    name: string;
    /** How many pieces of the equipment there are, each holding one meal */
    slots: number;
}

interface KitchenEquipment {
    uuid: string;
    name: string;
    category_label: string;
    /** In minutes, for each piece */
    cleaning_time: number;
    /** How many pieces are owned or stored in total */
    amount: number;
    in_use: number;
    dirty: number;
    clean: number;
}

interface Meal {
    uuid: string;
    recipe_uuid: string;
    name: string;
    status: MealStatus;
    prep_time: number;
    cooker_uuid: string | null;
    cooker_slot: number | null;
    cooking_minutes_left: number | null;
    expires_in_minutes: number | null;
    spoiled: boolean;
    portions_left: number;
}

/** A plated portion waiting to be brought to a table */
export interface Plate {
    uuid: string;
    recipe_uuid: string;
    name: string;
}

export interface OrderItem {
    uuid: string;
    recipe_uuid: string | null;
    name: string;
    customer_uuid: string | null;
    customer_name: string | null;
    price: number;
    served: boolean;
}

/** An order that is still being served or eaten */
export interface Order {
    uuid: string;
    table_uuid: string | null;
    status: 'open' | 'fulfilled';
    total_price: number;
    ordered_at: string;
    ordered_minutes_ago: number;
    fulfilled_minutes_ago: number | null;
    items: OrderItem[];
}

export interface ServiceState {
    clock: Clock;
    recipes: KitchenRecipe[];
    cookers: Cooker[];
    equipment: KitchenEquipment[];
    meals: Meal[];
    plates: Plate[];
    orders: Order[];
}

const MEAL_MIME = 'application/x-restaurant-meal';
/** Drag type for plated portions, which are dropped on tables */
export const PLATE_MIME = 'application/x-restaurant-plate';

let serviceUrl = '';
let state: ServiceState | null = null;

/** Called whenever the service state changes, so the floor can show the tables' orders */
const stateListeners: ((state: ServiceState) => void)[] = [];

/** The meal or plate being dragged, as drop targets can't read drag data until the drop itself */
let draggedMeal: Meal | null = null;
let draggedPlate: Plate | null = null;
let pendingScrap: Meal | null = null;

export function getServiceState(): ServiceState | null {
    return state;
}

export function getDraggedPlate(): Plate | null {
    return draggedPlate;
}

export function onServiceState(listener: (state: ServiceState) => void): void {
    stateListeners.push(listener);
    if (state !== null) listener(state);
}

//| Initialisation

export async function initKitchen(url: string): Promise<void> {
    serviceUrl = url;
    setupMealMenuClosing();

    // Done meals can be taken out of their cooker by dropping them in the serving area
    setupDropTarget(document.getElementById('kitchen-serving') as HTMLElement, MEAL_MIME, (uuid) => {
        post(`/meal/${uuid}/serve`);
    }, () => draggedMeal?.status === 'done');

    const response = await getData(serviceUrl);
    setState(response.data);
}

function setState(data: ServiceState): void {
    // The meal cards are rebuilt, so a menu open for one would point at a card that no longer exists
    closeMealMenu();
    state = data;
    renderClock();
    renderCookers();
    renderPreparation();
    renderServing();
    renderEquipment();
    stateListeners.forEach((listener) => listener(data));
}

//| Clock

function renderClock(): void {
    const clock = state!.clock;
    const input = document.getElementById('service-time') as HTMLInputElement;
    input.value = clock.time;

    const date = new Date(`${clock.date}T00:00:00`);
    document.getElementById('service-date')!.textContent = date.toLocaleDateString(undefined, {day: 'numeric', month: 'long', year: 'numeric'});
    document.getElementById('service-shift-total')!.textContent = formatMoney(clock.shift_total);
}

async function advanceTime(minutes: number): Promise<void> {
    const formData = new FormData();
    formData.append('advance', String(minutes));
    await post('/time', formData, false);
}

async function setServiceTime(time: string): Promise<void> {
    if (time === '' || time === state?.clock.time) return;

    const formData = new FormData();
    formData.append('time', time);
    if (!await post('/time', formData, false)) {
        // Put the saved time back, so the field doesn't show a time that wasn't accepted
        renderClock();
    }
}

//| Cooking

function renderCookers(): void {
    const container = document.getElementById('kitchen-cookers') as HTMLElement;
    const template = document.getElementById('cooker-slot-template') as HTMLTemplateElement;
    container.replaceChildren();

    for (const cooker of state!.cookers) {
        const meals = state!.meals.filter((meal) => meal.cooker_uuid === cooker.uuid);

        // A meal can still be in a slot the restaurant no longer has the equipment for, so that slot is shown too
        const slots = Math.max(cooker.slots, ...meals.map((meal) => meal.cooker_slot ?? 0));
        if (slots === 0) {
            container.appendChild(createNote(`${cooker.name}: none in stock`));
            continue;
        }

        for (let slot = 1; slot <= slots; slot++) {
            const element = template.content.firstElementChild!.cloneNode(true) as HTMLElement;
            setField(element, 'name', slots > 1 ? `${cooker.name} ${slot}` : cooker.name);

            const meal = meals.find((candidate) => candidate.cooker_slot === slot) ?? null;
            if (meal !== null) {
                element.classList.add('occupied');
                element.querySelector('[data-meal]')!.appendChild(createMealCard(meal));
            }

            // Only empty cookers take a prepped meal
            setupDropTarget(element, MEAL_MIME, (uuid) => cookMeal(uuid, cooker.uuid, slot),
                () => meal === null && draggedMeal?.status === 'prepped');

            container.appendChild(element);
        }
    }

    if (state!.cookers.length === 0) {
        container.appendChild(createNote('No cookers yet'));
    }
}

async function cookMeal(uuid: string, cookerUuid: string, slot: number): Promise<void> {
    const formData = new FormData();
    formData.append('cooker_uuid', cookerUuid);
    formData.append('slot', String(slot));
    await post(`/meal/${uuid}/cook`, formData);
}

//| Preparation

function renderPreparation(): void {
    const container = document.getElementById('kitchen-preparation') as HTMLElement;
    container.replaceChildren();

    const meals = state!.meals.filter((meal) => meal.status === 'planned' || meal.status === 'prepped');
    for (const meal of meals) {
        container.appendChild(createMealCard(meal));
    }

    if (meals.length === 0) {
        container.appendChild(createNote('No meals being prepared'));
    }
}

function openPlanMealModal(): void {
    const select = document.getElementById('meal-plan-recipe') as HTMLSelectElement;
    select.replaceChildren();
    for (const recipe of state!.recipes) {
        const label = `${recipe.name} (prep ${formatMinutes(recipe.prep_time)}, cook ${formatMinutes(recipe.cooking_time)})`;
        select.appendChild(new Option(label, recipe.uuid));
    }

    if (state!.recipes.length === 0) {
        select.appendChild(new Option('No recipes yet', ''));
    }

    openModal('meal-plan-modal');
}

async function planMeal(): Promise<void> {
    const form = document.getElementById('meal-plan-form') as HTMLFormElement;
    if (!form.reportValidity()) return;

    if (await post('/meal/plan', new FormData(form))) {
        closeModal('meal-plan-modal');
    }
}

//| Serving

function renderServing(): void {
    const container = document.getElementById('kitchen-serving') as HTMLElement;
    container.replaceChildren();

    const meals = state!.meals.filter((meal) => meal.status === 'serving');
    for (const meal of meals) {
        container.appendChild(createMealCard(meal));
    }

    // Plated portions, ready to be dragged to a table that ordered them
    if (state!.plates.length > 0) {
        const plates = document.createElement('div');
        plates.className = 'kitchen-plates';
        state!.plates.forEach((plate) => plates.appendChild(createPlateChip(plate)));
        container.appendChild(plates);
    }

    if (meals.length === 0 && state!.plates.length === 0) {
        container.appendChild(createNote('Nothing being served'));
    }
}

function createPlateChip(plate: Plate): HTMLElement {
    const chip = document.createElement('div');
    chip.className = 'inventory-item kitchen-plate';
    chip.draggable = true;
    chip.textContent = plate.name;

    chip.addEventListener('dragstart', (event) => {
        draggedPlate = plate;
        event.dataTransfer!.setData(PLATE_MIME, plate.uuid);
        event.dataTransfer!.effectAllowed = 'move';
    });
    chip.addEventListener('dragend', () => draggedPlate = null);

    chip.addEventListener('click', () => toggleMenu(chip, `${plate.name}, plated`, (option) => {
        option('Scrap plate', () => post(`/plate/${plate.uuid}/scrap`));
    }));

    return chip;
}

//| Equipment

/** Below these shares of clean pieces, equipment is shown as running low or as nearly out */
const LOW_CLEAN_SHARE = 0.25;
const CRITICAL_CLEAN_SHARE = 0.05;

function renderEquipment(): void {
    const list = document.getElementById('kitchen-equipment') as HTMLElement;
    list.replaceChildren();

    for (const piece of state!.equipment) {
        const row = document.createElement('li');
        row.className = 'kitchen-equipment-row';
        row.title = `${piece.category_label} — ${describeUsage(piece)}`;

        // Warns when few pieces are left clean, as dirty or in use pieces can't be used
        if (piece.amount > 0) {
            const share = piece.clean / piece.amount;
            row.classList.toggle('critical', share < CRITICAL_CLEAN_SHARE);
            row.classList.toggle('low', share >= CRITICAL_CLEAN_SHARE && share < LOW_CLEAN_SHARE);
        }

        const name = document.createElement('span');
        name.className = 'truncate';
        name.textContent = piece.name;

        const counts = document.createElement('span');
        counts.className = 'kitchen-equipment-counts';
        counts.append(
            createCount(piece.clean, 'clean'),
            createCount(piece.in_use, 'in use'),
            createCount(piece.dirty, 'dirty'),
        );

        row.append(name, counts);
        row.addEventListener('click', () => toggleEquipmentMenu(piece, row));
        list.appendChild(row);
    }

    if (state!.equipment.length === 0) {
        list.appendChild(createNote('No other equipment'));
    }

    const anyDirty = state!.equipment.some((piece) => piece.dirty > 0);
    (document.getElementById('kitchen-clean-all') as HTMLButtonElement).disabled = !anyDirty;
}

function createCount(amount: number, label: string): HTMLElement {
    const count = document.createElement('span');
    count.dataset.count = label;
    count.textContent = String(amount);
    count.title = `${amount} ${label}`;
    return count;
}

function describeUsage(piece: KitchenEquipment): string {
    return `${piece.clean} clean, ${piece.in_use} in use, ${piece.dirty} dirty`;
}

/** Opens the menu for a piece of equipment, where any number of its dirty pieces can be cleaned at once */
function toggleEquipmentMenu(piece: KitchenEquipment, anchor: HTMLElement): void {
    toggleMenu(anchor, `${piece.name}: ${describeUsage(piece)}`, (_option, options) => {
        if (piece.dirty === 0) {
            options.appendChild(createNote('Nothing to clean'));
            return;
        }

        const form = document.createElement('form');
        form.className = 'meal-menu-clean';

        const input = document.createElement('input');
        input.type = 'number';
        input.min = '1';
        input.max = String(piece.dirty);
        input.value = String(piece.dirty);
        input.required = true;
        input.className = 'underlined w-14';

        const button = document.createElement('button');
        button.type = 'submit';
        button.className = 'meal-menu-option';
        const label = () => {
            const amount = Math.max(1, Math.min(piece.dirty, parseInt(input.value, 10) || 1));
            button.textContent = `Clean (${formatMinutes(piece.cleaning_time * amount)})`;
        };
        input.addEventListener('input', label);
        label();

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            if (!form.reportValidity()) return;

            const formData = new FormData();
            formData.append('amount', input.value);
            closeMealMenu();
            post(`/equipment/${piece.uuid}/clean`, formData);
        });

        form.append(input, button);
        options.appendChild(form);
        input.focus();
        input.select();
    });
}

async function cleanAllEquipment(): Promise<void> {
    await post('/equipment/clean-all');
}

//| Meals

function createMealCard(meal: Meal): HTMLElement {
    const template = document.getElementById('meal-card-template') as HTMLTemplateElement;
    const card = template.content.firstElementChild!.cloneNode(true) as HTMLElement;
    const actions = card.querySelector('[data-actions]') as HTMLElement;

    setField(card, 'name', meal.name);
    setField(card, 'timer', describeTimer(meal));
    card.classList.toggle('spoiled', meal.spoiled);
    card.dataset.status = meal.status;

    switch (meal.status) {
        case 'planned':
            addAction(actions, `Prepare (${formatMinutes(meal.prep_time)})`, () => post(`/meal/${meal.uuid}/prepare`));
            addAction(actions, 'Cancel', () => post(`/meal/${meal.uuid}/cancel`));
            break;
        case 'prepped':
            addAction(actions, 'Cancel', () => openScrapModal(meal));
            break;
        default:
            // Meals in a cooker or being served open a menu of what can be done with them
            card.classList.add('clickable');
            card.addEventListener('click', () => toggleMealMenu(meal, card));
    }

    // Prepped meals go into a cooker, done meals into the serving area
    if (meal.status === 'prepped' || meal.status === 'done') {
        card.draggable = true;
        card.addEventListener('dragstart', (event) => {
            draggedMeal = meal;
            event.dataTransfer!.setData(MEAL_MIME, meal.uuid);
            event.dataTransfer!.effectAllowed = 'move';
        });
        card.addEventListener('dragend', () => draggedMeal = null);
    }

    return card;
}

//| Meal menu

/** The meal card the menu is open for, so clicking it again closes the menu */
let menuAnchor: HTMLElement | null = null;

/** Adds an option to the open menu, which closes the menu before performing its action */
export type AddMenuOption = (label: string, action: () => void) => void;

/**
 * Opens the small menu hovering over an element, or closes it when it is already open for that element
 * @param anchor The element the menu hovers over
 * @param status A line describing the element
 * @param build Fills the menu, either through the option adder or by adding to the options container directly
 */
export function toggleMenu(anchor: HTMLElement, status: string, build: (option: AddMenuOption, options: HTMLElement) => void): void {
    if (menuAnchor === anchor) {
        closeMealMenu();
        return;
    }

    const menu = document.getElementById('meal-menu') as HTMLElement;
    document.getElementById('meal-menu-status')!.textContent = status;

    const options = document.getElementById('meal-menu-options') as HTMLElement;
    options.replaceChildren();

    build((label, action) => {
        const button = document.createElement('button');
        button.className = 'meal-menu-option';
        button.setAttribute('role', 'menuitem');
        button.textContent = label;
        button.addEventListener('click', () => {
            closeMealMenu();
            action();
        });
        options.appendChild(button);
    }, options);

    menuAnchor = anchor;
    menu.classList.remove('hidden');
    positionMealMenu(menu, anchor);
}

function toggleMealMenu(meal: Meal, anchor: HTMLElement): void {
    toggleMenu(anchor, describeTimer(meal), (option) => {
        if (meal.status === 'cooking') {
            option(`Skip until done (${formatMinutes(meal.cooking_minutes_left ?? 0)})`, () => post(`/meal/${meal.uuid}/skip`));
        }
        if (meal.status === 'done') {
            option('Move to serving', () => post(`/meal/${meal.uuid}/serve`));
        }
        if (meal.status === 'done' || meal.status === 'serving') {
            const left = meal.portions_left === 1 ? 'last portion' : `${meal.portions_left} portions left`;
            option(`Plate a portion (${left})`, () => post(`/meal/${meal.uuid}/plate`));
        }
        option('Scrap meal', () => openScrapModal(meal));
    });
}

/** Places the menu centred just above its meal, or below it when there is no room above */
function positionMealMenu(menu: HTMLElement, anchor: HTMLElement): void {
    const gap = 6;
    const margin = 8;
    const rect = anchor.getBoundingClientRect();
    const width = menu.offsetWidth;
    const height = menu.offsetHeight;

    let top = rect.top - height - gap;
    if (top < margin) {
        top = rect.bottom + gap;
    }

    const left = Math.min(Math.max(rect.left + rect.width / 2 - width / 2, margin), window.innerWidth - width - margin);
    menu.style.top = `${top}px`;
    menu.style.left = `${left}px`;
}

export function closeMealMenu(): void {
    menuAnchor = null;
    document.getElementById('meal-menu')?.classList.add('hidden');
}

/** Closes the menu when clicking anywhere else, pressing escape, or scrolling it away from its meal */
function setupMealMenuClosing(): void {
    document.addEventListener('click', (event) => {
        const target = event.target as Node;
        const menu = document.getElementById('meal-menu') as HTMLElement;
        if (menuAnchor !== null && !menu.contains(target) && !menuAnchor.contains(target)) {
            closeMealMenu();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeMealMenu();
    });

    // Captured, so scrolling inside the kitchen panel closes it as well
    document.addEventListener('scroll', closeMealMenu, true);
    window.addEventListener('resize', closeMealMenu);
}

function openScrapModal(meal: Meal): void {
    pendingScrap = meal;
    document.getElementById('meal-scrap-name')!.textContent = meal.name;
    openModal('meal-scrap-modal');
}

async function confirmScrapMeal(): Promise<void> {
    if (pendingScrap === null) return;

    const meal = pendingScrap;
    pendingScrap = null;
    closeModal('meal-scrap-modal');

    await post(`/meal/${meal.uuid}/cancel`);
}

function describeTimer(meal: Meal): string {
    if (meal.status === 'cooking') {
        return `${formatMinutes(meal.cooking_minutes_left ?? 0)} left`;
    }

    if (meal.expires_in_minutes !== null && !meal.spoiled) {
        return meal.status === 'serving'
            ? `fresh for ${formatMinutes(meal.expires_in_minutes)}`
            : `good for ${formatMinutes(meal.expires_in_minutes)}`;
    }

    return '';
}

//| Helpers

/**
 * Posts to a service endpoint and shows the updated state it returns
 * @return Whether the request succeeded
 */
export async function post(path: string, formData: FormData | string = new FormData(), announce: boolean = true): Promise<boolean> {
    try {
        const response = await postOnce(`${serviceUrl}${path}`, formData);
        if (!response?.ok) return false;
        if (announce) response.announce();

        setState(response.data);
        return true;
    } catch {
        // The error has already been shown to the user
        return false;
    }
}

function addAction(container: HTMLElement, label: string, action: () => void): void {
    const button = document.createElement('button');
    button.className = 'interactive';
    button.textContent = label;
    button.addEventListener('click', action);
    container.appendChild(button);
}

function createNote(text: string): HTMLElement {
    const note = document.createElement('p');
    note.className = 'text-gray-400 text-sm';
    note.textContent = text;
    return note;
}

/** Formats minutes as for example "45m" or "1h 05m" */
export function formatMinutes(minutes: number): string {
    if (minutes < 60) return `${minutes}m`;

    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;
    return rest === 0 ? `${hours}h` : `${hours}h ${String(rest).padStart(2, '0')}m`;
}

(<any>window).advanceTime       = advanceTime;
(<any>window).setServiceTime    = setServiceTime;
(<any>window).openPlanMealModal = openPlanMealModal;
(<any>window).planMeal          = planMeal;
(<any>window).confirmScrapMeal  = confirmScrapMeal;
(<any>window).cleanAllEquipment = cleanAllEquipment;
