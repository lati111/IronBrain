import {setField, setupDropTarget} from "../shared";
import {EMPLOYEE_MIME, getDraggedEmployee, setupEmployeeDrag} from "./drag";
import {cloneTemplate, createNote} from "./elements";
import {canControl, getServiceState, isPlayer, postParameters} from "./state";
import {Employee, WorkStationCode} from "./types";

/** The staff, who are dragged into the work areas to work there and back with the rest of the staff to stop */

/** Employees work in the area they are dropped on, and stop when dropped back with the rest of the staff */
export function setupStaffDropTargets(): void {
    const areas: [string, WorkStationCode | null][] = [
        ['kitchen-staff-area', null],
        ['kitchen-prep-area', 'prep'],
        ['kitchen-cleaning-area', 'cleaning'],
    ];
    for (const [id, station] of areas) {
        setupDropTarget(document.getElementById(id) as HTMLElement, EMPLOYEE_MIME, (uuid) => assignEmployee(uuid, station), () => {
            const employee = getDraggedEmployee();
            return employee !== null && (employee.station !== station || employee.tables.length > 0);
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
        const working = employees.filter((candidate) => candidate.station === element.dataset.stationStaff);
        element.replaceChildren(...working.map(createDraggableEmployeeIcon));
    });
}

function createEmployeeElement(employee: Employee): HTMLElement {
    const element = cloneTemplate('employee-template');

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
        icon.dataset.station = employee.station;
    } else if (employee.tables.length > 0) {
        icon.dataset.station = 'tables';
    }

    return icon;
}

/** The employee's icon named after them, which can be dragged elsewhere by whoever controls them */
export function createDraggableEmployeeIcon(employee: Employee): HTMLElement {
    const icon = createEmployeeIcon(employee);
    icon.title = describeEmployee(employee);
    if (canControl(employee)) {
        icon.draggable = true;
        setupEmployeeDrag(icon, employee);
    }
    return icon;
}

async function assignEmployee(uuid: string, station: WorkStationCode | null): Promise<void> {
    await postParameters(`/employee/${uuid}/assign`, station !== null ? {station} : {}, false);
}
