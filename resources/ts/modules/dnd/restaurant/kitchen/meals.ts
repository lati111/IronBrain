import {openModal, closeModal} from "../../../../components/modal";
import {BoostTarget, setField, setupDropTarget} from "../shared";
import {getDraggedMeal, MEAL_MIME, setupBoostTarget, setupMealDrag} from "./drag";
import {addAction, cloneTemplate, createProgressBar, describeJobTimer, formatMinutes, makeReadOnly} from "./elements";
import {onRightClick, toggleMenu} from "./menu";
import {openPrioritizeMenu} from "./prioritize";
import {openQualityModal} from "./quality";
import {getServiceState, isPlayer, post} from "./state";
import {Meal, MissingMeal} from "./types";

/** The cards of the meals in the kitchen, and what can be done with a meal from its card and its menu */

let pendingScrap: Meal | null = null;

/** The meal waiting to have another put in and the meal to put in, while asking whether to plate the leftovers */
let pendingSupply: {meal: Meal, source: Meal, leftover: number} | null = null;

//| Meal cards

/** The boost target a meal is, by its status */
const MEAL_BOOST_TARGET: Record<Meal['status'], BoostTarget> = {
    planned: 'planned_meal',
    prepped: 'prepped_meal',
    cooking: 'cooking_meal',
    done:    'cooked_meal',
    serving: 'cooked_meal',
};

export function createMealCard(meal: Meal): HTMLElement {
    const card = cloneTemplate('meal-card-template');
    const actions = card.querySelector('[data-actions]') as HTMLElement;

    setField(card, 'name', meal.name);
    setField(card, 'quality', meal.quality !== null ? `Q${meal.quality}` : '');
    setField(card, 'timer', describeTimer(meal));
    setField(card, 'needs', meal.missing_meals.map((missing) => `${missing.name} ×${missing.portions}`).join(', '));
    card.classList.toggle('spoiled', meal.spoiled);
    card.dataset.status = meal.status;

    // Meals take the boosts for how far along they are: planned, prepped, cooking, or cooked once done
    setupBoostTarget(card, MEAL_BOOST_TARGET[meal.status], {meal_uuid: meal.uuid});

    // Meals still waiting on other meals take a finished one dropped on them
    if (meal.missing_meals.length > 0) {
        setupDropTarget(card, MEAL_MIME, (uuid) => supplyMeal(meal, uuid), () => {
            const dragged = getDraggedMeal();
            return dragged !== null && findMissingMeal(meal, dragged) !== null;
        });
    }

    switch (meal.status) {
        case 'planned': {
            // Queued meals show how long until they are prepped, which depends on who works at prep
            const job = getServiceState()!.stations.prep.jobs.find((candidate) => candidate.meal_uuid === meal.uuid) ?? null;
            if (job !== null) {
                card.dataset.queued = '';
                setField(card, 'timer', describeJobTimer(job.minutes_left));
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
        default:
            // Meals in a cooker or being served open a menu of what can be done with them
            if (meal.status === 'cooking') {
                card.appendChild(createProgressBar(meal.cooking_progress ?? 0));
            }
            card.classList.add('clickable');
            card.addEventListener('click', () => toggleMealMenu(meal, card));
    }

    if (meal.status === 'prepped' || meal.status === 'done' || meal.status === 'serving') {
        setupMealDrag(card, meal);
    }

    return isPlayer() ? makeReadOnly(card) : card;
}

function describeTimer(meal: Meal): string {
    const timer = describeBaseTimer(meal);
    if (meal.speed === null || meal.speed === 1) return timer;

    // Equipment and boosts make it go faster or slower than normal
    return timer === '' ? `×${meal.speed}` : `${timer} ×${meal.speed}`;
}

function describeBaseTimer(meal: Meal): string {
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

//| Meal menu

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

//| Meals in meals

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
    const source = getServiceState()!.meals.find((candidate) => candidate.uuid === sourceUuid) ?? null;
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

(<any>window).confirmSupplyMeal = confirmSupplyMeal;
(<any>window).confirmScrapMeal  = confirmScrapMeal;
