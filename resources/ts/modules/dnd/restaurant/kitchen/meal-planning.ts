import {openModal, closeModal} from "../../../../components/modal";
import {cloneTemplate, formatAmount, formatMinutes} from "./elements";
import {getServiceState, post} from "./state";
import {KitchenIngredientOption, KitchenRecipe, KitchenRecipeEquipment} from "./types";

/**
 * Planning a meal, choosing which variant of the recipe to make and which ingredients and equipment to make it with,
 * and putting a prepped meal in a cooker, choosing which variants of the cooking equipment to use
 */

/** One option of a choice, which is greyed out when there isn't enough of it */
interface Choice {
    label: string;
    value: string;
    disabled?: boolean;
}

/** Where a dropped meal is going, while asking which variants of its cooking equipment to use */
let pendingCook: {uuid: string, cookerUuid: string, slot: number} | null = null;

function getRecipes(): KitchenRecipe[] {
    return getServiceState()!.recipes;
}

function findRecipe(uuid: string | undefined): KitchenRecipe | undefined {
    return getRecipes().find((candidate) => candidate.uuid === uuid);
}

//| Planning

function openPlanMealModal(): void {
    const select = document.getElementById('meal-plan-recipe') as HTMLSelectElement;
    select.replaceChildren();
    // Variants are chosen once their base recipe is
    for (const recipe of getRecipes().filter((candidate) => candidate.parent_uuid === null)) {
        select.appendChild(new Option(describePlannedRecipe(recipe), recipe.uuid));
    }

    if (getRecipes().length === 0) {
        select.appendChild(new Option('No recipes yet', ''));
    }

    renderMealPlanVariants();
    openModal('meal-plan-modal');
}

/** Describes a recipe to plan with how long it takes, such as "Stew (prep 10m, cook 20m)" */
function describePlannedRecipe(recipe: KitchenRecipe, name: string = recipe.name): string {
    return `${name} (prep ${formatMinutes(recipe.prep_time)}, cook ${formatMinutes(recipe.cooking_time)})`;
}

/** Asks which variant of the chosen recipe to prepare, defaulting to the recipe itself, when it has any */
function renderMealPlanVariants(): void {
    const baseUuid = (document.getElementById('meal-plan-recipe') as HTMLSelectElement).value;
    const base = findRecipe(baseUuid);
    const variants = getRecipes().filter((candidate) => candidate.parent_uuid === baseUuid);

    const select = document.getElementById('meal-plan-variant') as HTMLSelectElement;
    select.replaceChildren();
    if (base !== undefined) {
        select.appendChild(new Option(describePlannedRecipe(base, `${base.name} (base)`), base.uuid));
    }
    for (const variant of variants) {
        select.appendChild(new Option(describePlannedRecipe(variant), variant.uuid));
    }
    select.value = baseUuid;
    document.getElementById('meal-plan-variant-field')!.classList.toggle('hidden', variants.length === 0);

    renderMealPlanChoices();
}

/** Asks which ingredient to use for each category the chosen recipe takes, listing those with enough in stock first */
function renderMealPlanChoices(): void {
    const container = document.getElementById('meal-plan-choices') as HTMLElement;
    // The variant chosen, which is the recipe itself when it has none
    const recipe = findRecipe((document.getElementById('meal-plan-variant') as HTMLSelectElement).value);

    container.replaceChildren();

    // Ingredients with variants default to the one the recipe takes, with those short of stock greyed out
    for (const requirement of recipe?.ingredients ?? []) {
        if (requirement.options.length < 2) continue;

        container.appendChild(createChoiceField(
            `${requirement.options[0].name} (${formatAmount(requirement.amount)})`,
            `ingredients[${requirement.ingredient_uuid}]`,
            requirement.options.map((option) => toIngredientChoice(option, requirement.amount)),
            requirement.ingredient_uuid,
        ));
    }

    for (const requirement of recipe?.categories ?? []) {
        const enough = (option: KitchenIngredientOption) => Number(option.available >= requirement.amount);
        const options = [...requirement.options].sort((a, b) => enough(b) - enough(a));

        container.appendChild(createChoiceField(
            `${requirement.category} (${formatAmount(requirement.amount)})`,
            `choices[${requirement.category}]`,
            [
                {label: `Choose a ${requirement.category.toLowerCase()}...`, value: ''},
                ...options.map((option) => toIngredientChoice(option, requirement.amount)),
            ],
            null,
        ));
    }

    renderEquipmentChoices(container, recipe, 'prep', false);
}

/** Describes an ingredient to choose with how much is in stock, and its quality when it has one, such as "Salt (Q2, 5 in stock)" */
function toIngredientChoice(option: KitchenIngredientOption, amount: number): Choice {
    const quality = option.quality !== 0 ? `Q${option.quality}, ` : '';
    return {
        label: `${option.name} (${quality}${formatAmount(option.available)} in stock)`,
        value: option.uuid,
        disabled: option.available < amount,
    };
}

/**
 * Asks which piece to use for each piece of equipment of a stage that has variants, defaulting to the equipment the
 * recipe takes. Pieces without enough clean are greyed out
 * @returns How many pieces of equipment can be chosen
 */
function renderEquipmentChoices(container: HTMLElement, recipe: KitchenRecipe | undefined, stage: 'prep' | 'cooking', clear = true): number {
    if (clear) container.replaceChildren();

    // The same equipment can be taken more than once, as a container and not
    const needed = new Map<string, KitchenRecipeEquipment>();
    for (const entry of recipe?.equipment ?? []) {
        if (entry.stage !== stage || entry.options.length < 2) continue;
        const existing = needed.get(entry.equipment_uuid);
        needed.set(entry.equipment_uuid, existing !== undefined ? {...existing, amount: existing.amount + entry.amount} : entry);
    }

    for (const entry of needed.values()) {
        container.appendChild(createChoiceField(
            `${entry.options[0].name} (${entry.amount})`,
            `equipment[${entry.equipment_uuid}]`,
            entry.options.map((option) => ({
                label: `${option.name} (${option.clean} clean)`,
                value: option.uuid,
                disabled: option.clean < entry.amount,
            })),
            entry.equipment_uuid,
        ));
    }

    return needed.size;
}

/**
 * Creates a labelled select of the choices
 * @param selected The value chosen by default, or null for the first choice
 */
function createChoiceField(label: string, name: string, choices: Choice[], selected: string | null): HTMLElement {
    const field = cloneTemplate('meal-plan-choice-template');
    field.querySelector('label')!.textContent = label;

    const select = field.querySelector('select') as HTMLSelectElement;
    select.name = name;
    for (const choice of choices) {
        const option = new Option(choice.label, choice.value);
        option.disabled = choice.disabled ?? false;
        select.appendChild(option);
    }
    if (selected !== null) select.value = selected;

    return field;
}

async function planMeal(): Promise<void> {
    const form = document.getElementById('meal-plan-form') as HTMLFormElement;
    if (!form.reportValidity()) return;

    if (await post('/meal/plan', new FormData(form))) {
        closeModal('meal-plan-modal');
    }
}

//| Cooking

/** Cooks a meal straight away, unless its cooking equipment has variants, in which case it asks which to use first */
export async function chooseCookingEquipment(uuid: string, cookerUuid: string, slot: number): Promise<void> {
    const meal = getServiceState()!.meals.find((candidate) => candidate.uuid === uuid);
    const container = document.getElementById('meal-cook-equipment') as HTMLElement;
    if (renderEquipmentChoices(container, findRecipe(meal?.recipe_uuid), 'cooking') === 0) {
        await cookMeal(uuid, cookerUuid, slot);
        return;
    }

    pendingCook = {uuid, cookerUuid, slot};
    openModal('meal-cook-modal');
}

async function confirmCook(): Promise<void> {
    if (pendingCook === null) return;

    const form = document.getElementById('meal-cook-form') as HTMLFormElement;
    if (await cookMeal(pendingCook.uuid, pendingCook.cookerUuid, pendingCook.slot, new FormData(form))) {
        pendingCook = null;
        closeModal('meal-cook-modal');
    }
}

async function cookMeal(uuid: string, cookerUuid: string, slot: number, formData = new FormData()): Promise<boolean> {
    formData.append('cooker_uuid', cookerUuid);
    formData.append('slot', String(slot));
    return await post(`/meal/${uuid}/cook`, formData);
}

(<any>window).openPlanMealModal      = openPlanMealModal;
(<any>window).renderMealPlanVariants = renderMealPlanVariants;
(<any>window).renderMealPlanChoices  = renderMealPlanChoices;
(<any>window).planMeal               = planMeal;
(<any>window).confirmCook            = confirmCook;
