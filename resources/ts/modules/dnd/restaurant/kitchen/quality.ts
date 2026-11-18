import {openModal, closeModal} from "../../../../components/modal";
import {formatBonus} from "./elements";
import {getServiceState, isOnline, post} from "./state";
import {Meal} from "./types";

/** Asking how a finished meal turned out, which sets the quality it is served at */

/** The meal the quality modal is asking about, and the meals whose question was put off until the page reloads */
let qualityMeal: Meal | null = null;
const qualityPutOff = new Set<string>();

/**
 * Whether the one looking is asked how meals turned out: a player cooking, while they have the page open, or whoever
 * runs the service when no such player is. With several cooks online the same one is asked everywhere, so they aren't
 * all asked at once. Whoever runs the service can still set it from the meal's menu
 */
function isAsked(): boolean {
    const state = getServiceState()!;
    const cooks = state.employees.filter((employee) => employee.stations.includes('cooking') && employee.player_uuid !== null && isOnline(employee.player_uuid))
        .map((employee) => employee.player_uuid!).sort();

    return cooks.length > 0 ? state.viewer.user_uuid === cooks[0] : !state.viewer.player;
}

/** Asks how a finished meal turned out, for the first one that hasn't got a quality yet */
export function askForQuality(): void {
    if (qualityMeal !== null || !isAsked()) return;

    const meal = getServiceState()!.meals.find((candidate) => candidate.finished && candidate.quality === null && !qualityPutOff.has(candidate.uuid));
    if (meal !== undefined) {
        openQualityModal(meal);
    }
}

export function openQualityModal(meal: Meal): void {
    qualityMeal = meal;
    document.getElementById('meal-quality-title')!.textContent = `${meal.name} is ready`;

    const input = document.getElementById('meal-quality-input') as HTMLInputElement;
    // The quality asked for is how well it turned out, before anything is added or multiplied
    input.value = meal.quality_roll !== null ? String(meal.quality_roll) : '';

    const extras = [];
    if (meal.equipment_multiplier !== 0) extras.push(`equipment ×${(1 + meal.equipment_multiplier).toFixed(2)} on the roll`);
    if (meal.equipment_bonus !== 0) extras.push(`equipment ${formatBonus(meal.equipment_bonus)}`);
    if (meal.cooking_quality !== 0) extras.push(`cooking ${formatBonus(meal.cooking_quality)}`);
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
