import {openModal, closeModal} from "../../../../components/modal";
import {formatBonus} from "./elements";
import {getServiceState, isPlayer, post} from "./state";
import {Meal} from "./types";

/** Asking how a finished meal turned out, which sets the quality it is served at */

/** The meal the quality modal is asking about, and the meals whose question was put off until the page reloads */
let qualityMeal: Meal | null = null;
const qualityPutOff = new Set<string>();

/** Asks how a finished meal turned out, for the first one that hasn't got a quality yet */
export function askForQuality(): void {
    // How a meal turned out is up to whoever runs the kitchen
    if (qualityMeal !== null || isPlayer()) return;

    const meal = getServiceState()!.meals.find((candidate) => candidate.finished && candidate.quality === null && !qualityPutOff.has(candidate.uuid));
    if (meal !== undefined) {
        openQualityModal(meal);
    }
}

export function openQualityModal(meal: Meal): void {
    qualityMeal = meal;
    document.getElementById('meal-quality-title')!.textContent = `${meal.name} is ready`;

    const input = document.getElementById('meal-quality-input') as HTMLInputElement;
    // The quality asked for is how well it turned out, before what boosts and its ingredients added
    input.value = meal.quality !== null ? String(meal.quality - meal.quality_bonus - meal.ingredient_quality) : '';

    const extras = [];
    if (meal.ingredient_quality !== 0) extras.push(`ingredients ${formatBonus(meal.ingredient_quality)}`);
    if (meal.quality_bonus !== 0) extras.push(`boosts ${formatBonus(meal.quality_bonus)}`);
    const hint = document.getElementById('meal-quality-hint') as HTMLElement;
    hint.textContent = extras.length > 0 ? `Added on top: ${extras.join(', ')}` : '';
    hint.classList.toggle('hidden', extras.length === 0);
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

(<any>window).saveQuality   = saveQuality;
(<any>window).putOffQuality = putOffQuality;
