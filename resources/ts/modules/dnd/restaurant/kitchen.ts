import {formatMoney, setField, setupDropTarget} from "./shared";
import {getDraggedMeal, MEAL_MIME, setupBoostTarget, setupPlateDrag} from "./kitchen/drag";
import {
    addAction, cloneTemplate, createNote, createProgressBar, describeJobTimer, formatMinutes, makeReadOnly,
} from "./kitchen/elements";
import {renderEquipment} from "./kitchen/equipment";
import {chooseCookingEquipment} from "./kitchen/meal-planning";
import {createMealCard} from "./kitchen/meals";
import {closeMealMenu, onRightClick, setupMealMenuClosing, toggleMenu} from "./kitchen/menu";
import {openPrioritizeMenu} from "./kitchen/prioritize";
import {askForQuality} from "./kitchen/quality";
import {renderStaff, setupStaffDropTargets} from "./kitchen/staff";
import {
    getServiceState, isPlayer, loadServiceState, onServiceState, post, postParameters, startRefreshing,
} from "./kitchen/state";
import {Meal, Plate, ServiceState, WorkJob, WorkStationCode} from "./kitchen/types";

/** The service clock and the kitchen of the service page */

//| Initialisation

export async function initKitchen(url: string): Promise<void> {
    // Added before anything else listens, so the kitchen is shown first whenever the state changes
    onServiceState(renderKitchen);
    setupMealMenuClosing();

    // Done meals can be taken out of their cooker by dropping them in the serving area
    setupDropTarget(document.getElementById('kitchen-serving') as HTMLElement, MEAL_MIME, (uuid) => {
        post(`/meal/${uuid}/serve`);
    }, () => getDraggedMeal()?.status === 'done');

    // A boost on an area is dropped on the area itself
    setupBoostTarget(document.getElementById('kitchen-prep-area') as HTMLElement, 'area', {area: 'prep'});
    setupBoostTarget(document.getElementById('kitchen-cooking-area') as HTMLElement, 'area', {area: 'cooking'});
    setupBoostTarget(document.getElementById('kitchen-cleaning-area') as HTMLElement, 'area', {area: 'cleaning'});

    setupStaffDropTargets();

    await loadServiceState(url);
    startRefreshing();
}

function renderKitchen(): void {
    // The meal cards are rebuilt, so a menu open for one would point at a card that no longer exists
    closeMealMenu();
    renderClock();
    renderActiveBoosts();
    renderCookers();
    renderPreparation();
    renderServing();
    renderEquipment();
    renderStaff();
    renderStationProgress();
    askForQuality();
}

function getState(): ServiceState {
    return getServiceState()!;
}

//| Boosts

/** Shows the boosts active now, each with what it is on and how long it has left */
function renderActiveBoosts(): void {
    const container = document.getElementById('kitchen-boosts') as HTMLElement;
    container.replaceChildren();

    for (const boost of getState().active_boosts) {
        const chip = document.createElement('span');
        chip.className = 'kitchen-boost';
        chip.textContent = `⚡ ${boost.name} · ${boost.target_name} · ${formatMinutes(boost.minutes_left)}`;
        chip.title = `+${boost.speed_bonus} speed on ${boost.target_name} for another ${formatMinutes(boost.minutes_left)}`;
        container.appendChild(chip);
    }

    container.classList.toggle('hidden', getState().active_boosts.length === 0);
}

//| Clock

function renderClock(): void {
    const clock = getState().clock;
    const input = document.getElementById('service-time') as HTMLInputElement;
    input.value = clock.time;
    input.disabled = isPlayer();

    const date = new Date(`${clock.date}T00:00:00`);
    document.getElementById('service-date')!.textContent = date.toLocaleDateString(undefined, {day: 'numeric', month: 'long', year: 'numeric'});
    document.getElementById('service-shift-total')!.textContent = formatMoney(clock.shift_total);
}

async function advanceTime(minutes: number): Promise<void> {
    await postParameters('/time', {advance: String(minutes)}, false);
}

async function setServiceTime(time: string): Promise<void> {
    if (time === '' || time === getServiceState()?.clock.time) return;

    if (!await postParameters('/time', {time}, false)) {
        // Put the saved time back, so the field doesn't show a time that wasn't accepted
        renderClock();
    }
}

//| Cooking

function renderCookers(): void {
    const container = document.getElementById('kitchen-cookers') as HTMLElement;
    container.replaceChildren();

    for (const cooker of getState().cookers) {
        const meals = getState().meals.filter((meal) => meal.cooker_uuid === cooker.uuid);

        // A meal can still be in a slot the restaurant no longer has the equipment for, so that slot is shown too
        const slots = Math.max(cooker.slots, ...meals.map((meal) => meal.cooker_slot ?? 0));
        if (slots === 0) {
            container.appendChild(createNote(`${cooker.name}: none in stock`));
            continue;
        }

        for (let slot = 1; slot <= slots; slot++) {
            const meal = meals.find((candidate) => candidate.cooker_slot === slot) ?? null;
            container.appendChild(createCookerSlot(slots > 1 ? `${cooker.name} ${slot}` : cooker.name, cooker.uuid, slot, meal));
        }
    }

    if (getState().cookers.length === 0) {
        container.appendChild(createNote('No cookers yet'));
    }
}

function createCookerSlot(name: string, cookerUuid: string, slot: number, meal: Meal | null): HTMLElement {
    const element = cloneTemplate('cooker-slot-template');
    setField(element, 'name', name);

    if (meal !== null) {
        element.classList.add('occupied');
        element.querySelector('[data-meal]')!.appendChild(createMealCard(meal));
    }

    // Only empty cookers take a prepped meal
    setupDropTarget(element, MEAL_MIME, (uuid) => chooseCookingEquipment(uuid, cookerUuid, slot),
        () => meal === null && getDraggedMeal()?.status === 'prepped');
    // A boost on equipment dropped here is on this one cooker only
    setupBoostTarget(element, 'equipment', {equipment_uuid: cookerUuid, cooker_slot: String(slot)});

    return element;
}

//| Preparation

function renderPreparation(): void {
    const container = document.getElementById('kitchen-preparation') as HTMLElement;
    const meals = getState().meals.filter((meal) => meal.status === 'planned' || meal.status === 'prepped');
    // Conversions are worked on in the prep area alongside the meals
    const conversions = getState().stations.prep.jobs.filter((job) => job.conversion !== null);

    container.replaceChildren(...meals.map(createMealCard), ...conversions.map(createConversionCard));

    if (meals.length === 0 && conversions.length === 0) {
        container.appendChild(createNote('No meals being prepared'));
    }
}

/** Shows a conversion being done in the prep area, which can be cancelled to get its ingredients back */
function createConversionCard(job: WorkJob): HTMLElement {
    const card = cloneTemplate('meal-card-template');
    const conversion = job.conversion!;
    const name = `${conversion.input_amount} ${conversion.input_name} → ${conversion.output_amount} ${conversion.output_name}`;

    setField(card, 'name', name);
    setField(card, 'timer', describeJobTimer(job.minutes_left));
    card.dataset.status = 'planned';
    card.dataset.queued = '';
    card.appendChild(createProgressBar(job.progress));
    addAction(card.querySelector('[data-actions]') as HTMLElement, 'Cancel', () => post(`/work/${job.uuid}/cancel`));
    onRightClick(card, () => openPrioritizeMenu(card, name, [job.uuid]));

    return isPlayer() ? makeReadOnly(card) : card;
}

//| Serving

function renderServing(): void {
    const container = document.getElementById('kitchen-serving') as HTMLElement;
    const meals = getState().meals.filter((meal) => meal.status === 'serving');
    const plates = getState().plates;
    container.replaceChildren(...meals.map(createMealCard));

    // Plated portions, ready to be dragged to a table that ordered them
    if (plates.length > 0) {
        const list = document.createElement('div');
        list.className = 'kitchen-plates';
        list.append(...plates.map(createPlateChip));
        container.appendChild(list);
    }

    if (meals.length === 0 && plates.length === 0) {
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

    setupPlateDrag(chip, plate);
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

//| Work areas

/** Fills the bars showing how far along the whole queue of the prep and cleaning areas is */
function renderStationProgress(): void {
    document.querySelectorAll<HTMLElement>('[data-station-progress]').forEach((bar) => {
        const progress = getState().stations[bar.dataset.stationProgress as WorkStationCode].progress;
        bar.classList.toggle('hidden', progress === null);
        (bar.firstElementChild as HTMLElement).style.width = `${Math.round((progress ?? 0) * 100)}%`;
        bar.title = progress !== null ? `${Math.round(progress * 100)}% of the queue done` : '';
    });
}

(<any>window).advanceTime    = advanceTime;
(<any>window).setServiceTime = setServiceTime;
