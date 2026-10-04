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

/** An ingredient, with how much is in the storage spaces and how much in the pile brought in during service */
export interface ServiceIngredient {
    uuid: string;
    name: string;
    icon: string | null;
    stored: number;
    pile: number;
}

export interface KitchenRecipe {
    uuid: string;
    name: string;
    /** What the recipe is ordered at: its sale price while on sale */
    price: number;
    /** The usual price of the recipe */
    base_price: number;
    on_sale: boolean;
    prep_time: number;
    cooking_time: number;
    /** Ingredients taken by category, of which the one to use is chosen when planning the meal */
    categories: KitchenRecipeCategory[];
}

interface KitchenRecipeCategory {
    category: string;
    amount: number;
    /** The ingredients of the category, with how much of each is in storage and the pile together */
    options: {uuid: string, name: string, available: number}[];
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
    /** The category of the meal's recipe, by which other recipes can require it */
    recipe_category: string | null;
    status: MealStatus;
    prep_time: number;
    cooker_uuid: string | null;
    cooker_slot: number | null;
    cooking_minutes_left: number | null;
    /** How far along cooking is, from 0 to 1, or null when not cooking */
    cooking_progress: number | null;
    expires_in_minutes: number | null;
    spoiled: boolean;
    portions_left: number;
    /** How well the meal turned out, asked for once it is finished */
    quality: number | null;
    /** Whether the meal is done cooking or ready to serve */
    finished: boolean;
    /** Other meals still to be put in before it can be cooked */
    missing_meals: MissingMeal[];
}

/** Another meal a meal still waits on: of a specific recipe, or of any recipe of a category */
interface MissingMeal {
    recipe_uuid: string | null;
    category: string | null;
    name: string;
    portions: number;
}

/** A plated portion waiting to be brought to a table */
export interface Plate {
    uuid: string;
    recipe_uuid: string;
    name: string;
    /** The quality of the meal it was plated from */
    quality: number | null;
    /** Minutes until the portion spoils, as the meal would in the serving area */
    expires_in_minutes: number | null;
    spoiled: boolean;
}

export interface OrderItem {
    uuid: string;
    recipe_uuid: string | null;
    name: string;
    customer_uuid: string | null;
    customer_name: string | null;
    price: number;
    served: boolean;
    /** Whether a plate was set aside for it, which the waiter is bringing */
    on_the_way: boolean;
    consumed: boolean;
}

/** An order that is still being served or eaten */
export interface Order {
    uuid: string;
    table_uuid: string | null;
    /** Taking while the waiter takes it, then open until everything is served */
    status: 'taking' | 'open' | 'fulfilled';
    /** What the items are worth together */
    total_price: number;
    tip: number;
    discount: number;
    /** What the table pays once the order is finished: the items, less the discount, plus the tip */
    payable: number;
    ordered_at: string;
    ordered_minutes_ago: number;
    fulfilled_minutes_ago: number | null;
    items: OrderItem[];
}

/** The work areas employees can work in */
type WorkStationCode = 'prep' | 'cleaning';

export interface Employee {
    uuid: string;
    name: string;
    icon: string | null;
    /** The work area the employee works in, or null while idle or waiting tables */
    station: WorkStationCode | null;
    /** The uuids of the tables the employee waits, when waiting tables rather than working in a work area */
    tables: string[];
    /** The task the employee is on, such as "Prepping Stew" */
    task: string;
    /** The player controlling the employee, if any */
    player_uuid: string | null;
    player_name: string | null;
}

/** Who is looking at the service. Players only control the employees assigned to them */
interface Viewer {
    user_uuid: string | null;
    player: boolean;
}

/** A job queued in a work area: prepping a meal, or cleaning pieces of equipment */
interface WorkJob {
    uuid: string;
    meal_uuid: string | null;
    equipment_uuid: string | null;
    /** A conversion of an ingredient done in the prep area */
    conversion: ConversionJob | null;
    amount: number;
    /** How many minutes the job takes at normal speed */
    work: number;
    /** How far along the job is, from 0 to 1 */
    progress: number;
    /** Minutes until the job is done, counting the jobs before it, or null while nobody works in the area */
    minutes_left: number | null;
}

interface ConversionJob {
    input_name: string;
    input_amount: number;
    output_name: string;
    output_amount: number;
}

/** A conversion of an ingredient into another, done in the prep area during service */
export interface ServiceConversion {
    uuid: string;
    input_name: string;
    output_name: string;
    /** How much of the output each piece of the input becomes */
    output_amount: number;
    /** How many minutes converting a piece takes at normal speed */
    minutes: number;
    /** How much of the input is stored and in the pile */
    available: number;
}

interface WorkStationState {
    /** The combined speed of the employees working there */
    speed: number;
    /** How far along the whole queue is, from 0 to 1, or null while there is nothing to do */
    progress: number | null;
    jobs: WorkJob[];
}

export interface ServiceState {
    clock: Clock;
    ingredients: ServiceIngredient[];
    conversions: ServiceConversion[];
    recipes: KitchenRecipe[];
    cookers: Cooker[];
    equipment: KitchenEquipment[];
    meals: Meal[];
    plates: Plate[];
    employees: Employee[];
    stations: Record<WorkStationCode, WorkStationState>;
    /** The jobs of the waiters: taking orders and bringing plates */
    waiting: WaitingJob[];
    orders: Order[];
    viewer: Viewer;
}

/** A job of a table's waiter: taking an order there, or bringing a plated portion */
export interface WaitingJob {
    uuid: string;
    table_uuid: string;
    order_uuid: string | null;
    served_meal_uuid: string | null;
    /** Such as "Taking an order at Booth" or "Bringing Stew to Booth" */
    task: string;
    /** How far along the job is, from 0 to 1 */
    progress: number;
    /** Minutes until the job is done, counting the waiter's jobs before it, or null while nobody waits the table */
    minutes_left: number | null;
}

const MEAL_MIME = 'application/x-restaurant-meal';
/** Drag type for employees, which are dropped on a work area, on a table to wait it, or back with the staff */
export const EMPLOYEE_MIME = 'application/x-restaurant-employee';
/** Drag type for plated portions, which are dropped on tables */
export const PLATE_MIME = 'application/x-restaurant-plate';

let serviceUrl = '';
let state: ServiceState | null = null;

/** Called whenever the service state changes, so the floor can show the tables' orders */
const stateListeners: ((state: ServiceState) => void)[] = [];

/** The meal or plate being dragged, as drop targets can't read drag data until the drop itself */
let draggedMeal: Meal | null = null;
let draggedPlate: Plate | null = null;
let draggedEmployee: Employee | null = null;
let pendingScrap: Meal | null = null;

/** The meal the quality modal is asking about, and the meals whose question was put off until the page reloads */
let qualityMeal: Meal | null = null;
const qualityPutOff = new Set<string>();

/** Whether the one looking is a player, who only controls the employees assigned to them */
export function isPlayer(): boolean {
    return state?.viewer.player ?? false;
}

/** Whether the one looking may move an employee around: anyone but a player, and a player their own employees */
export function canControl(employee: Employee): boolean {
    return !isPlayer() || employee.player_uuid === state!.viewer.user_uuid;
}

/** How often the service is refreshed, in milliseconds, so changes made by others show up */
const REFRESH_INTERVAL = 5000;
/** Counts the changes posted, so a refresh that started before one doesn't overwrite what it returned */
let changeCount = 0;
let dragging = false;

/**
 * Whether the page is left alone, so refreshing it doesn't get in the way: it is visible, and nothing is being dragged,
 * asked for in a modal, or opened in a menu
 */
export function isIdle(): boolean {
    return document.visibilityState === 'visible' && !dragging && menuAnchor === null
        && document.querySelector('.modal:not(.hidden)') === null;
}

/** Fetches the service again when the page is left alone, unless something was changed in the meantime */
async function refreshState(): Promise<void> {
    if (!isIdle()) return;

    const count = changeCount;
    try {
        const response = await getData(serviceUrl);
        if (count === changeCount && isIdle()) {
            setState(response.data);
        }
    } catch {
        // Tried again on the next refresh
    }
}

/** Turns an interactive card into one that can only be looked at, by copying it without its listeners */
function makeReadOnly(card: HTMLElement): HTMLElement {
    const copy = card.cloneNode(true) as HTMLElement;
    copy.draggable = false;
    copy.classList.remove('clickable');
    copy.querySelector('[data-actions]')?.replaceChildren();
    return copy;
}

export function getServiceState(): ServiceState | null {
    return state;
}

export function getDraggedPlate(): Plate | null {
    return draggedPlate;
}

export function getDraggedEmployee(): Employee | null {
    return draggedEmployee;
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

    // Employees work in the area they are dropped on, and stop when dropped back with the rest of the staff
    const areas: [string, WorkStationCode | null][] = [
        ['kitchen-staff-area', null],
        ['kitchen-prep-area', 'prep'],
        ['kitchen-cleaning-area', 'cleaning'],
    ];
    for (const [id, station] of areas) {
        setupDropTarget(document.getElementById(id) as HTMLElement, EMPLOYEE_MIME, (uuid) => assignEmployee(uuid, station),
            () => draggedEmployee !== null && (draggedEmployee.station !== station || draggedEmployee.tables.length > 0));
    }

    const response = await getData(serviceUrl);
    setState(response.data);

    document.addEventListener('dragstart', () => dragging = true);
    document.addEventListener('dragend', () => dragging = false);
    document.addEventListener('drop', () => dragging = false);
    setInterval(refreshState, REFRESH_INTERVAL);
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
    renderStaff();
    renderStationProgress();
    stateListeners.forEach((listener) => listener(data));
    askForQuality();
}

//| Clock

function renderClock(): void {
    const clock = state!.clock;
    const input = document.getElementById('service-time') as HTMLInputElement;
    input.value = clock.time;
    input.disabled = isPlayer();

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

    // Conversions are worked on in the prep area alongside the meals
    const conversions = state!.stations.prep.jobs.filter((job) => job.conversion !== null);
    for (const job of conversions) {
        container.appendChild(createConversionCard(job));
    }

    if (meals.length === 0 && conversions.length === 0) {
        container.appendChild(createNote('No meals being prepared'));
    }
}

/** Shows a conversion being done in the prep area, which can be cancelled to get its ingredients back */
function createConversionCard(job: WorkJob): HTMLElement {
    const template = document.getElementById('meal-card-template') as HTMLTemplateElement;
    const card = template.content.firstElementChild!.cloneNode(true) as HTMLElement;
    const conversion = job.conversion!;
    const name = `${conversion.input_amount} ${conversion.input_name} → ${conversion.output_amount} ${conversion.output_name}`;

    setField(card, 'name', name);
    setField(card, 'timer', job.minutes_left !== null ? `${formatMinutes(job.minutes_left)} left` : 'queued');
    card.dataset.status = 'planned';
    card.dataset.queued = '';
    card.appendChild(createProgressBar(job.progress));
    addAction(card.querySelector('[data-actions]') as HTMLElement, 'Cancel', () => post(`/work/${job.uuid}/cancel`));
    onRightClick(card, () => openPrioritizeMenu(card, name, [job.uuid]));

    return isPlayer() ? makeReadOnly(card) : card;
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

    renderMealPlanChoices();
    openModal('meal-plan-modal');
}

/** Asks which ingredient to use for each category the chosen recipe takes, listing those with enough in stock first */
function renderMealPlanChoices(): void {
    const container = document.getElementById('meal-plan-choices') as HTMLElement;
    const template = document.getElementById('meal-plan-choice-template') as HTMLTemplateElement;
    const recipeUuid = (document.getElementById('meal-plan-recipe') as HTMLSelectElement).value;
    const recipe = state!.recipes.find((candidate) => candidate.uuid === recipeUuid);

    container.replaceChildren();
    for (const requirement of recipe?.categories ?? []) {
        const field = template.content.firstElementChild!.cloneNode(true) as HTMLElement;
        field.querySelector('label')!.textContent = `${requirement.category} (${formatAmount(requirement.amount)})`;

        const select = field.querySelector('select') as HTMLSelectElement;
        select.name = `choices[${requirement.category}]`;
        select.appendChild(new Option(`Choose a ${requirement.category.toLowerCase()}...`, ''));
        const options = [...requirement.options].sort((a, b) => Number(b.available >= requirement.amount) - Number(a.available >= requirement.amount));
        for (const option of options) {
            const element = new Option(`${option.name} (${formatAmount(option.available)} in stock)`, option.uuid);
            element.disabled = option.available < requirement.amount;
            select.appendChild(element);
        }

        container.appendChild(field);
    }
}

/** Shows an ingredient amount with at most two decimals */
function formatAmount(amount: number): string {
    return String(Math.round(amount * 100) / 100);
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
    chip.classList.toggle('spoiled', plate.spoiled);
    chip.textContent = describePlate(plate);

    // Plates are brought to tables by whoever runs the floor
    if (isPlayer()) return chip;
    chip.draggable = true;

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

/** Describes a plated portion with its quality and how long it stays fresh, such as "Stew · Q12 · 25m" */
function describePlate(plate: Plate): string {
    const parts = [plate.name];
    if (plate.quality !== null) parts.push(`Q${plate.quality}`);
    if (plate.spoiled) {
        parts.push('spoiled');
    } else if (plate.expires_in_minutes !== null) {
        parts.push(formatMinutes(plate.expires_in_minutes));
    }

    return parts.join(' · ');
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

        // Pieces queued for cleaning, with how long until the last of them is clean
        const queue = getCleaningJobs(piece);
        const cleaning = document.createElement('span');
        cleaning.className = 'kitchen-equipment-cleaning';
        if (queue.length > 0) {
            const minutes = queue[queue.length - 1].minutes_left;
            const amount = queue.reduce((total, job) => total + job.amount, 0);
            cleaning.textContent = minutes !== null ? `${amount} · ${formatMinutes(minutes)}` : String(amount);
        }

        const counts = document.createElement('span');
        counts.className = 'kitchen-equipment-counts';
        counts.append(
            createCount(piece.clean, 'clean'),
            createCount(piece.in_use, 'in use'),
            createCount(piece.dirty, 'dirty'),
        );

        row.append(name, cleaning, counts);
        if (queue.length > 0) {
            const work = queue.reduce((total, job) => total + job.work, 0);
            const done = queue.reduce((total, job) => total + job.work * job.progress, 0);
            row.appendChild(createProgressBar(work > 0 ? done / work : 0));
        }
        if (!isPlayer()) {
            row.addEventListener('click', () => toggleEquipmentMenu(piece, row));
        }
        if (queue.length > 0 && !isPlayer()) {
            // Prioritising one piece moves every piece of the equipment, so the first stands for all
            onRightClick(row, () => openPrioritizeMenu(row, `${piece.name}: cleaning`, [queue[0].uuid]));
        }
        list.appendChild(row);
    }

    if (state!.equipment.length === 0) {
        list.appendChild(createNote('No other equipment'));
    }

    const anyToClean = state!.equipment.some((piece) => getUnqueuedDirty(piece) > 0);
    const cleanAll = document.getElementById('kitchen-clean-all') as HTMLButtonElement | null;
    if (cleanAll !== null) cleanAll.disabled = !anyToClean;
}

function getCleaningJobs(piece: KitchenEquipment): WorkJob[] {
    return state!.stations.cleaning.jobs.filter((job) => job.equipment_uuid === piece.uuid);
}

/** How many dirty pieces aren't queued for cleaning yet */
function getUnqueuedDirty(piece: KitchenEquipment): number {
    const queued = getCleaningJobs(piece).reduce((total, job) => total + job.amount, 0);
    return Math.max(0, piece.dirty - queued);
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
        const available = getUnqueuedDirty(piece);
        if (available === 0) {
            options.appendChild(createNote('Nothing to clean'));
            return;
        }

        const form = document.createElement('form');
        form.className = 'meal-menu-clean';

        const input = document.createElement('input');
        input.type = 'number';
        input.min = '1';
        input.max = String(available);
        input.value = String(available);
        input.required = true;
        input.className = 'underlined w-14';

        const button = document.createElement('button');
        button.type = 'submit';
        button.className = 'meal-menu-option';
        button.textContent = 'Clean';

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

//| Staff

function renderStaff(): void {
    const container = document.getElementById('kitchen-staff') as HTMLElement;
    container.replaceChildren();
    for (const employee of state!.employees) {
        container.appendChild(createEmployeeElement(employee));
    }

    if (state!.employees.length === 0) {
        container.appendChild(createNote('No employees yet'));
    }

    // Each work area shows who works there, who can be dragged from there as well
    document.querySelectorAll<HTMLElement>('[data-station-staff]').forEach((element) => {
        element.replaceChildren();
        for (const employee of state!.employees.filter((candidate) => candidate.station === element.dataset.stationStaff)) {
            const icon = createEmployeeIcon(employee);
            icon.title = describeEmployee(employee);
            if (canControl(employee)) {
                icon.draggable = true;
                setupEmployeeDrag(icon, employee);
            }
            element.appendChild(icon);
        }
    });
}

function createEmployeeElement(employee: Employee): HTMLElement {
    const template = document.getElementById('employee-template') as HTMLTemplateElement;
    const element = template.content.firstElementChild!.cloneNode(true) as HTMLElement;

    element.querySelector('.kitchen-employee-icon')!.replaceWith(createEmployeeIcon(employee));
    setField(element, 'name', employee.name);
    setField(element, 'task', employee.task);
    if (employee.station !== null) {
        element.dataset.station = employee.station;
    }
    element.title = describeEmployee(employee);

    // Players only move their own employees, which are marked out for them
    if (canControl(employee)) {
        setupEmployeeDrag(element, employee);
        element.classList.toggle('own', isPlayer());
    } else {
        element.draggable = false;
        element.classList.add('locked');
    }
    return element;
}

/** Names the employee, and the player controlling them, if any */
export function describeEmployee(employee: Employee): string {
    return employee.player_name !== null ? `${employee.name} (${employee.player_name})` : employee.name;
}

/** The employee's icon, or the first letter of their name when they have none */
export function createEmployeeIcon(employee: Employee): HTMLElement {
    const icon = document.createElement('div');
    icon.className = 'kitchen-employee-icon';
    if (employee.icon !== null) {
        icon.style.backgroundImage = `url(${JSON.stringify(employee.icon)})`;
    } else {
        icon.textContent = employee.name.charAt(0).toUpperCase();
    }

    // The ring around the icon shows where they work, with waiting tables counting as a place of its own
    if (employee.station !== null) {
        icon.dataset.station = employee.station;
    } else if (employee.tables.length > 0) {
        icon.dataset.station = 'tables';
    }

    return icon;
}

export function setupEmployeeDrag(element: HTMLElement, employee: Employee): void {
    element.addEventListener('dragstart', (event) => {
        draggedEmployee = employee;
        event.dataTransfer!.setData(EMPLOYEE_MIME, employee.uuid);
        event.dataTransfer!.effectAllowed = 'move';
        event.stopPropagation();
    });
    element.addEventListener('dragend', () => draggedEmployee = null);
}

async function assignEmployee(uuid: string, station: WorkStationCode | null): Promise<void> {
    const formData = new FormData();
    if (station !== null) formData.append('station', station);
    await post(`/employee/${uuid}/assign`, formData, false);
}

//| Meals

function createMealCard(meal: Meal): HTMLElement {
    const template = document.getElementById('meal-card-template') as HTMLTemplateElement;
    const card = template.content.firstElementChild!.cloneNode(true) as HTMLElement;
    const actions = card.querySelector('[data-actions]') as HTMLElement;

    setField(card, 'name', meal.name);
    setField(card, 'quality', meal.quality !== null ? `Q${meal.quality}` : '');
    setField(card, 'timer', describeTimer(meal));
    setField(card, 'needs', meal.missing_meals.map((missing) => `${missing.name} ×${missing.portions}`).join(', '));
    card.classList.toggle('spoiled', meal.spoiled);
    card.dataset.status = meal.status;

    // Meals still waiting on other meals take a finished one dropped on them
    if (meal.missing_meals.length > 0) {
        setupDropTarget(card, MEAL_MIME, (uuid) => supplyMeal(meal, uuid),
            () => draggedMeal !== null && findMissingMeal(meal, draggedMeal) !== null);
    }

    switch (meal.status) {
        case 'planned': {
            // Queued meals show how long until they are prepped, which depends on who works at prep
            const job = state!.stations.prep.jobs.find((candidate) => candidate.meal_uuid === meal.uuid) ?? null;
            if (job !== null) {
                card.dataset.queued = '';
                setField(card, 'timer', job.minutes_left !== null ? `${formatMinutes(job.minutes_left)} left` : 'queued');
                card.appendChild(createProgressBar(job.progress));
                onRightClick(card, () => openPrioritizeMenu(card, meal.name, [job.uuid]));
            } else {
                addAction(actions, `Prep (${formatMinutes(meal.prep_time)})`, () => post(`/meal/${meal.uuid}/prepare`));
            }
            addAction(actions, 'Cancel', () => post(`/meal/${meal.uuid}/cancel`));
            break;
        }
        case 'prepped':
            addAction(actions, 'Cancel', () => openScrapModal(meal));
            break;
        case 'cooking':
            card.appendChild(createProgressBar(meal.cooking_progress ?? 0));
            card.classList.add('clickable');
            card.addEventListener('click', () => toggleMealMenu(meal, card));
            break;
        default:
            // Meals in a cooker or being served open a menu of what can be done with them
            card.classList.add('clickable');
            card.addEventListener('click', () => toggleMealMenu(meal, card));
    }

    // Prepped meals go into a cooker, done meals into the serving area, and finished meals into a meal taking them
    if (meal.status === 'prepped' || meal.status === 'done' || meal.status === 'serving') {
        card.draggable = true;
        card.addEventListener('dragstart', (event) => {
            draggedMeal = meal;
            event.dataTransfer!.setData(MEAL_MIME, meal.uuid);
            event.dataTransfer!.effectAllowed = 'move';
        });
        card.addEventListener('dragend', () => draggedMeal = null);
    }

    return isPlayer() ? makeReadOnly(card) : card;
}

//| Meals in meals

/** The meal waiting to have another put in and the meal to put in, while asking whether to plate the leftovers */
let pendingSupply: {meal: Meal, source: Meal, leftover: number} | null = null;

/** Finds what a meal still waits on that a finished meal can be put in for */
function findMissingMeal(meal: Meal, source: Meal): MissingMeal | null {
    if (source.uuid === meal.uuid || !source.finished || source.spoiled) return null;

    // Specific recipes come first, as the server matches them first as well
    return meal.missing_meals.find((missing) => missing.recipe_uuid === source.recipe_uuid)
        ?? meal.missing_meals.find((missing) => missing.recipe_uuid === null && missing.category === source.recipe_category)
        ?? null;
}

/** Puts a finished meal into a meal taking it, first asking to plate the portions that would be left over */
function supplyMeal(meal: Meal, sourceUuid: string): void {
    const source = state!.meals.find((candidate) => candidate.uuid === sourceUuid) ?? null;
    const missing = source !== null ? findMissingMeal(meal, source) : null;
    if (source === null || missing === null) return;

    const leftover = source.portions_left - missing.portions;
    if (leftover > 0) {
        pendingSupply = {meal, source, leftover};
        document.getElementById('meal-supply-text')!.textContent = `${missing.portions} of the ${source.portions_left} portions of `
            + `${source.name} go into ${meal.name}. The ${leftover === 1 ? 'remaining portion' : `remaining ${leftover} portions`} will be plated.`;
        openModal('meal-supply-modal');
        return;
    }

    // Too few portions are left for the server to explain
    postSupply(meal, source, false);
}

async function confirmSupplyMeal(): Promise<void> {
    if (pendingSupply === null) return;

    const {meal, source} = pendingSupply;
    pendingSupply = null;
    closeModal('meal-supply-modal');
    await postSupply(meal, source, true);
}

async function postSupply(meal: Meal, source: Meal, plateRemaining: boolean): Promise<void> {
    const formData = new FormData();
    formData.append('source_uuid', source.uuid);
    if (plateRemaining) formData.append('plate_remaining', '1');
    await post(`/meal/${meal.uuid}/supply`, formData);
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
        if (meal.finished) {
            option(meal.quality === null ? 'Set quality' : `Change quality (Q${meal.quality})`, () => openQualityModal(meal));
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

//| Quality

/** Asks how a finished meal turned out, for the first one that hasn't got a quality yet */
function askForQuality(): void {
    // How a meal turned out is up to whoever runs the kitchen
    if (qualityMeal !== null || isPlayer()) return;

    const meal = state!.meals.find((candidate) => candidate.finished && candidate.quality === null && !qualityPutOff.has(candidate.uuid));
    if (meal !== undefined) {
        openQualityModal(meal);
    }
}

function openQualityModal(meal: Meal): void {
    qualityMeal = meal;
    document.getElementById('meal-quality-title')!.textContent = `${meal.name} is ready`;

    const input = document.getElementById('meal-quality-input') as HTMLInputElement;
    input.value = meal.quality !== null ? String(meal.quality) : '';
    openModal('meal-quality-modal');
    input.focus();
}

async function saveQuality(): Promise<void> {
    const form = document.getElementById('meal-quality-form') as HTMLFormElement;
    if (qualityMeal === null || !form.reportValidity()) return;

    const meal = qualityMeal;
    closeModal('meal-quality-modal');
    qualityMeal = null;

    if (!await post(`/meal/${meal.uuid}/quality`, new FormData(form), false)) {
        // Asked again, so the quality isn't lost to a failed save
        openQualityModal(meal);
    }
}

/** Puts the question off until the page reloads, after which it can still be set from the meal's menu */
function putOffQuality(): void {
    if (qualityMeal !== null) qualityPutOff.add(qualityMeal.uuid);

    qualityMeal = null;
    closeModal('meal-quality-modal');
    askForQuality();
}

//| Scrapping

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
    changeCount++;
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

/**
 * Creates a thin bar along the bottom of its container, filled as far as something is along
 * @param fraction How far along, from 0 to 1
 */
function createProgressBar(fraction: number): HTMLElement {
    const bar = document.createElement('div');
    bar.className = 'kitchen-progress';

    const fill = document.createElement('div');
    fill.style.width = `${Math.round(Math.min(1, Math.max(0, fraction)) * 100)}%`;
    bar.appendChild(fill);
    return bar;
}

/** Fills the bars showing how far along the whole queue of the prep and cleaning areas is */
function renderStationProgress(): void {
    document.querySelectorAll<HTMLElement>('[data-station-progress]').forEach((bar) => {
        const progress = state!.stations[bar.dataset.stationProgress as WorkStationCode].progress;
        bar.classList.toggle('hidden', progress === null);
        (bar.firstElementChild as HTMLElement).style.width = `${Math.round((progress ?? 0) * 100)}%`;
        bar.title = progress !== null ? `${Math.round(progress * 100)}% of the queue done` : '';
    });
}

//| Prioritising

/**
 * Calls an action when an element is right clicked, instead of the browser's own menu
 * @param element The element
 * @param action The action
 */
export function onRightClick(element: HTMLElement, action: () => void): void {
    element.addEventListener('contextmenu', (event) => {
        event.preventDefault();
        event.stopPropagation();
        action();
    });
}

/**
 * Opens the menu over a job, from where it is moved to the front of its queue so it is worked on next
 * @param anchor The element the menu hovers over
 * @param status A line describing the job
 * @param jobUuids The jobs that can be prioritised, labelled by the given labels when there are several
 * @param labels The label of each job, when there are several to choose from
 */
export function openPrioritizeMenu(anchor: HTMLElement, status: string, jobUuids: string[], labels: string[] = []): void {
    toggleMenu(anchor, status, (option) => {
        jobUuids.forEach((uuid, index) => {
            option(labels[index] !== undefined ? `Prioritize ${labels[index]}` : 'Prioritize', () => prioritizeWork(uuid));
        });
    });
}

async function prioritizeWork(uuid: string): Promise<void> {
    await post(`/work/${uuid}/prioritize`, new FormData(), false);
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
(<any>window).renderMealPlanChoices = renderMealPlanChoices;
(<any>window).confirmSupplyMeal = confirmSupplyMeal;
(<any>window).confirmScrapMeal  = confirmScrapMeal;
(<any>window).saveQuality       = saveQuality;
(<any>window).putOffQuality     = putOffQuality;
(<any>window).cleanAllEquipment = cleanAllEquipment;
