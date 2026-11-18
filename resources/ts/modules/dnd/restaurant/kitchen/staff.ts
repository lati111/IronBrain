import {setField, setupDropTarget} from "../shared";
import {EMPLOYEE_MIME, getDraggedEmployee, getDraggedFrom, setupEmployeeDrag} from "./drag";
import {cloneTemplate, createNote} from "./elements";
import {canControl, getServiceState, isPlayer, postParameters} from "./state";
import {Employee, StaffArea} from "./types";

/** The staff, who are dragged into the work areas to work there and back with the rest of the staff to stop */

/** Whether shift was held when the employee was dropped, which has them work the area besides the one they work in */
let combining = false;

/**
 * Employees work in the area they are dropped on, and stop when dropped back with the rest of the staff. Holding shift
 * while dropping them on prep or cooking has them work both, prepping at half speed. An icon dragged out of one of the
 * areas back to the staff only leaves that area
 */
export function setupStaffDropTargets(): void {
    document.addEventListener('drop', (event) => combining = event.shiftKey, true);

    const areas: [string, StaffArea | null][] = [
        ['kitchen-staff-area', null],
        ['kitchen-prep-area', 'prep'],
        ['kitchen-cooking-area', 'cooking'],
        ['kitchen-cleaning-area', 'cleaning'],
    ];
    for (const [id, station] of areas) {
        setupDropTarget(document.getElementById(id) as HTMLElement, EMPLOYEE_MIME, (uuid) => assignEmployee(uuid, station), () => {
            const employee = getDraggedEmployee();
            if (employee === null) return false;

            return station === null
                ? employee.stations.length > 0 || employee.tables.length > 0
                : !employee.stations.includes(station) || employee.tables.length > 0;
        });
    }
}

export function renderStaff(): void {
    const employees = getServiceState()!.employees;
    const container = document.getElementById('kitchen-staff') as HTMLElement;
    container.replaceChildren(...employees.map(createEmployeeElement));

    if (employees.length === 0) {
        container.appendChild(createNote('No employees yet'));
    }

    // Each work area shows who works there, who can be dragged from there as well
    document.querySelectorAll<HTMLElement>('[data-station-staff]').forEach((element) => {
        const area = element.dataset.stationStaff as StaffArea;
        const working = employees.filter((candidate) => candidate.stations.includes(area));
        element.replaceChildren(...working.map((employee) => createDraggableEmployeeIcon(employee, area)));
    });
}

function createEmployeeElement(employee: Employee): HTMLElement {
    const element = cloneTemplate('employee-template');

    element.querySelector('.kitchen-employee-icon')!.replaceWith(createEmployeeIcon(employee));
    setField(element, 'name', employee.name);
    setField(element, 'task', employee.task);
    if (employee.station !== null) {
        element.dataset.station = employee.stations.length > 1 ? 'combined' : employee.station;
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
function describeEmployee(employee: Employee): string {
    return employee.player_name !== null ? `${employee.name} (${employee.player_name})` : employee.name;
}

/** The employee's icon, or the first letter of their name when they have none */
function createEmployeeIcon(employee: Employee): HTMLElement {
    const icon = document.createElement('div');
    icon.className = 'kitchen-employee-icon';
    if (employee.icon !== null) {
        icon.style.backgroundImage = `url(${JSON.stringify(employee.icon)})`;
    } else {
        icon.textContent = employee.name.charAt(0).toUpperCase();
    }

    // The ring around the icon shows where they work, with waiting tables counting as a place of its own
    if (employee.station !== null) {
        icon.dataset.station = employee.stations.length > 1 ? 'combined' : employee.station;
    } else if (employee.tables.length > 0) {
        icon.dataset.station = 'tables';
    }

    return icon;
}

/** The employee's icon named after them, which can be dragged elsewhere by whoever controls them */
export function createDraggableEmployeeIcon(employee: Employee, from: StaffArea | null = null): HTMLElement {
    const icon = createEmployeeIcon(employee);
    icon.title = describeEmployee(employee);
    if (canControl(employee)) {
        icon.draggable = true;
        setupEmployeeDrag(icon, employee, from);
    }
    return icon;
}

async function assignEmployee(uuid: string, station: StaffArea | null): Promise<void> {
    const parameters: Record<string, string> = {};
    if (station !== null) {
        parameters.station = station;
        if (combining) parameters.combine = '1';
    } else if (getDraggedFrom() !== null) {
        parameters.leave = getDraggedFrom()!;
    }

    await postParameters(`/employee/${uuid}/assign`, parameters, false);
}
