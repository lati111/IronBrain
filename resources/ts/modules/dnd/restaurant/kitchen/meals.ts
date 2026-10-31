import {openModal, closeModal} from "../../../../components/modal";
import {BoostTarget, setField, setupDropTarget} from "../shared";
import {getDraggedMeal, MEAL_MIME, setupBoostTarget, setupMealDrag} from "./drag";
import {addAction, cloneTemplate, createProgressBar, describeJobTimer, formatMinutes} from "./elements";
import {onRightClick, toggleMenu} from "./menu";
import {openPrioritizeMenu} from "./prioritize";
import {openQualityModal} from "./quality";
import {getServiceState, isPlayer, mayWaitAny, mayWorkAt, post} from "./state";
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

    // Meals still waiting on other meals take a finished one dropped on them, by whoever works at prep
    if (meal.missing_meals.length > 0) {
        setupDropTarget(card, MEAL_MIME, (uuid) => supplyMeal(meal, uuid), () => {
            const dragged = getDraggedMeal();
            return mayWorkAt('prep') && dragged !== null && findMissingMeal(meal, dragged) !== null;
        });
    }

    // Players do what the areas their employees work in let them, while cancelling stays with whoever runs the service
    switch (meal.status) {
        case 'planned': {
            // Queued meals show how long until they are prepped, which depends on who works at prep
            const job = getServiceState()!.stations.prep.jobs.find((candidate) => candidate.meal_uuid === meal.uuid) ?? null;
            if (job !== null) {
                card.dataset.queued = '';
                setField(card, 'timer', describeJobTimer(job.minutes_left));
                card.appendChild(createProgressBar(job.progress));
                if (!isPlayer()) {
                    onRightClick(card, () => openPrioritizeMenu(card, meal.name, [job.uuid]));
                }
            } else if (mayWorkAt('prep')) {
                addAction(actions, `Prep (${formatMinutes(meal.prep_time)})`, () => post(`/meal/${meal.uuid}/prepare`));
            }
            if (!isPlayer()) {
                addAction(actions, 'Cancel', () => post(`/meal/${meal.uuid}/cancel`));
            }
            break;
        }
        case 'prepped':
            if (!isPlayer()) {
                addAction(actions, 'Cancel', () => openScrapModal(meal));
            }
            break;
        default:
            // Meals in a cooker or being served open a menu of what can be done with them, when anything can
            if (meal.status === 'cooking') {
                card.appendChild(createProgressBar(meal.cooking_progress ?? 0));
            }
            if (getMealMenuOptions(meal).length > 0) {
                card.classList.add('clickable');
                card.addEventListener('click', () => toggleMealMenu(meal, card));
            }
    }

    // Prepped meals go into a cooker and done ones to serving by the cooks, while finished meals go into other meals
    // by whoever works at prep
    const draggable = (meal.status === 'prepped' && mayWorkAt('cooking'))
        || (meal.status === 'done' && (mayWorkAt('cooking') || mayWorkAt('prep')))
        || (meal.status === 'serving' && mayWorkAt('prep'));
    if (draggable) {
        setupMealDrag(card, meal);
    }

    return card;
}

function describeTimer(meal: Meal): string {
    const timer = describeBaseTimer(meal);
    if (meal.speed === null || meal.speed === 1) return timer;

    // Equipment and boosts make it go faster or slower than normal
    return timer === '' ? `×${meal.speed}` : `${timer} ×${meal.speed}`;
}

function describeBaseTimer(meal: Meal): string {
    if (meal.status === 'cooking') {
        // Nothing cooks while nobody works in the cooking area
        return meal.cooking_minutes_left !== null ? `${formatMinutes(meal.cooking_minutes_left)} left` : 'waiting for a cook';
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
        for (const [label, action] of getMealMenuOptions(meal)) {
            option(label, action);
        }
    });
}

/**
 * Gets what can be done with a meal in a cooker or being served, by the one looking. Cooks move meals to serving,
 * cooks and waiters plate them, and the rest is up to whoever runs the service
 * @returns Each option's label and action
 */
function getMealMenuOptions(meal: Meal): [string, () => void][] {
    const options: [string, () => void][] = [];
    const runsService = !isPlayer();

    if (meal.status === 'cooking' && runsService && meal.cooking_minutes_left !== null) {
        options.push([`Skip until done (${formatMinutes(meal.cooking_minutes_left)})`, () => post(`/meal/${meal.uuid}/skip`)]);
    }
    if (meal.status === 'done' && mayWorkAt('cooking')) {
        options.push(['Move to serving', () => post(`/meal/${meal.uuid}/serve`)]);
    }
    if ((meal.status === 'done' || meal.status === 'serving') && (mayWorkAt('cooking') || mayWaitAny())) {
        const left = meal.portions_left === 1 ? 'last portion' : `${meal.portions_left} portions left`;
        options.push([`Plate a portion (${left})`, () => post(`/meal/${meal.uuid}/plate`)]);
    }
    if (meal.finished && runsService) {
        options.push([meal.quality === null ? 'Set quality' : `Change quality (Q${meal.quality})`, () => openQualityModal(meal)]);
    }
    if (runsService) {
        options.push(['Scrap meal', () => openScrapModal(meal)]);
    }

    return options;
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
