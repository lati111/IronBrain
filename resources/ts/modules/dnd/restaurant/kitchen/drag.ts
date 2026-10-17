import {setupDropTarget} from "../shared";
import {postParameters} from "./state";
import {Employee, Meal, Plate, ServiceBoost} from "./types";

/**
 * What can be dragged around the service page. The item being dragged is kept as well, as drop targets can't read
 * drag data until the drop itself
 */

export const MEAL_MIME = 'application/x-restaurant-meal';
/** Drag type for employees, which are dropped on a work area, on a table to wait it, or back with the staff */
export const EMPLOYEE_MIME = 'application/x-restaurant-employee';
/** Drag type for plated portions, which are dropped on tables */
export const PLATE_MIME = 'application/x-restaurant-plate';
/** Boosts are activated by dropping them on what they boost */
export const BOOST_MIME = 'application/x-restaurant-boost';

let draggedMeal: Meal | null = null;
let draggedPlate: Plate | null = null;
let draggedEmployee: Employee | null = null;
let draggedBoost: ServiceBoost | null = null;

export function getDraggedMeal(): Meal | null {
    return draggedMeal;
}

export function getDraggedPlate(): Plate | null {
    return draggedPlate;
}

export function getDraggedEmployee(): Employee | null {
    return draggedEmployee;
}

/**
 * Makes an element draggable as an item of a drag type, keeping the item while it is dragged
 * @param element The element to drag
 * @param mime The drag type
 * @param uuid The uuid the drop target gets
 * @param keep Keeps the item as the one being dragged
 * @param forget Forgets the item again once the drag ends
 * @param effect Whether the item is moved, or copied as with boosts that can be used again
 */
function setupDrag(element: HTMLElement, mime: string, uuid: string, keep: () => void, forget: () => void, effect: 'move' | 'copy' = 'move'): void {
    element.addEventListener('dragstart', (event) => {
        keep();
        event.dataTransfer!.setData(mime, uuid);
        event.dataTransfer!.effectAllowed = effect;
    });
    element.addEventListener('dragend', forget);
}

/** Prepped meals go into a cooker, done meals into the serving area, and finished meals into a meal taking them */
export function setupMealDrag(card: HTMLElement, meal: Meal): void {
    card.draggable = true;
    setupDrag(card, MEAL_MIME, meal.uuid, () => draggedMeal = meal, () => draggedMeal = null);
}

export function setupPlateDrag(chip: HTMLElement, plate: Plate): void {
    chip.draggable = true;
    setupDrag(chip, PLATE_MIME, plate.uuid, () => draggedPlate = plate, () => draggedPlate = null);
}

/** Boosts are dragged from the sidebar, so the kitchen can tell what they may be dropped on */
export function setupBoostDrag(row: HTMLElement, boost: ServiceBoost): void {
    row.draggable = true;
    setupDrag(row, BOOST_MIME, boost.uuid, () => draggedBoost = boost, () => draggedBoost = null, 'copy');
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

/** Sets up an element to take a boost of a kind dropped on it, activating it there */
export function setupBoostTarget(element: HTMLElement, target: ServiceBoost['target'], parameters: Record<string, string>): void {
    setupDropTarget(element, BOOST_MIME, (uuid) => postParameters(`/boost/${uuid}/activate`, parameters),
        () => draggedBoost?.target === target);
}
