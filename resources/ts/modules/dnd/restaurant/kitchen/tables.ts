import {setField, setupDropTarget} from "../shared";
import {OrderTable} from "../orders";
import {EMPLOYEE_MIME, getDraggedEmployee, getDraggedPlate, PLATE_MIME} from "./drag";
import {formatMinutes, pluralize} from "./elements";
import {onRightClick} from "./menu";
import {describeOrder} from "./order-details";
import {openPrioritizeMenu} from "./prioritize";
import {createDraggableEmployeeIcon} from "./staff";
import {canControl, getServiceState, isPlayer, postParameters} from "./state";
import {Employee, Order} from "./types";

/** What a table on the floor shows of the service: its waiter, the waiter's jobs, and how its orders are coming along */

/**
 * Fills a table's element with its waiter and orders, and lets it take plates and waiters dropped on it
 * @param element The table's element
 * @param table The table
 */
export function renderTableService(element: HTMLElement, table: OrderTable): void {
    const orders = getTableOrders(table);
    renderOrderIndicator(element, orders);
    renderWaiter(element, table);
    renderWaiterJob(element, table);

    // A plated portion can be brought to the table when it is still waiting for one of that recipe
    setupDropTarget(element, PLATE_MIME, (uuid) => {
        postParameters(`/plate/${uuid}/deliver`, {table_uuid: table.uuid});
    }, () => {
        // Only items without a plate on its way yet, and only when someone waits the table to bring it
        const plate = getDraggedPlate();
        return !isPlayer() && plate !== null && getWaiter(table) !== null && orders.some((order) => order.status === 'open'
            && order.items.some((item) => !item.served && !item.on_the_way && item.recipe_uuid === plate.recipe_uuid));
    });

    // An employee dropped on the table waits it, taking its orders. Players can't take a table from someone else's waiter
    setupDropTarget(element, EMPLOYEE_MIME, (uuid) => assignWaiter(table, uuid), () => {
        const employee = getDraggedEmployee();
        const waiter = getWaiter(table);
        return employee !== null && !employee.tables.includes(table.uuid) && (waiter === null || canControl(waiter));
    });
}

//| Waiters

/** The employee waiting a table, who takes its orders */
export function getWaiter(table: OrderTable): Employee | null {
    return getServiceState()?.employees.find((employee) => employee.tables.includes(table.uuid)) ?? null;
}

/** Shows who waits the table, whose icon can be dragged to another table or back to the staff */
function renderWaiter(element: HTMLElement, table: OrderTable): void {
    const slot = element.querySelector('[data-waiter]') as HTMLElement;
    slot.replaceChildren();

    const waiter = getWaiter(table);
    if (waiter !== null) {
        slot.appendChild(createDraggableEmployeeIcon(waiter));
    }
}

/** Shows the job the waiter is on at the table, with its progress and how long it takes until it is done */
function renderWaiterJob(element: HTMLElement, table: OrderTable): void {
    const container = element.querySelector('[data-waiter-job]') as HTMLElement;
    const jobs = (getServiceState()?.waiting ?? []).filter((job) => job.table_uuid === table.uuid);
    container.classList.toggle('hidden', jobs.length === 0);
    if (jobs.length === 0) return;

    // Jobs are named without their table, such as "Taking an order" rather than "Taking an order at Booth"
    const describeJob = (task: string) => task.replace(/ (at|to) .*$/, '');

    const job = jobs[0];
    const more = jobs.length > 1 ? ` (+${jobs.length - 1})` : '';
    const time = job.minutes_left !== null ? formatMinutes(job.minutes_left) : 'no waiter';
    setField(container, 'job', `${describeJob(job.task)}${more} · ${time}`);
    container.title = jobs.map((candidate) => candidate.task).join('\n');
    (container.querySelector('[data-waiter-job-fill]') as HTMLElement).style.width = `${Math.round(job.progress * 100)}%`;

    // Any of the table's jobs can be moved to the front of its waiter's queue, by whoever runs the floor
    if (isPlayer()) return;
    onRightClick(container, () => openPrioritizeMenu(container, `${table.name}: waiter`,
        jobs.map((candidate) => candidate.uuid),
        jobs.length > 1 ? jobs.map((candidate) => describeJob(candidate.task).toLowerCase()) : []));
}

export async function assignWaiter(table: OrderTable, employeeUuid: string | null): Promise<void> {
    await postParameters(`/table/${table.uuid}/waiter`, employeeUuid !== null ? {employee_uuid: employeeUuid} : {}, false);
}

//| Orders

export function getTableOrders(table: OrderTable): Order[] {
    return (getServiceState()?.orders ?? []).filter((order) => order.table_uuid === table.uuid);
}

/**
 * Shows how long ago the table ordered, or once everything is served, how long ago that was
 */
function renderOrderIndicator(element: HTMLElement, orders: Order[]): void {
    const indicator = element.querySelector('[data-field="order"]') as HTMLElement;
    // Orders still being taken wait as well
    const waiting = orders.filter((order) => order.status === 'open' || order.status === 'taking');
    const fulfilled = orders.filter((order) => order.status === 'fulfilled');

    indicator.classList.toggle('waiting', waiting.length > 0);
    indicator.classList.toggle('fulfilled', waiting.length === 0 && fulfilled.length > 0);

    if (waiting.length > 0) {
        // The longest waiting order is the one that matters most
        indicator.textContent = `Ordered ${formatMinutes(Math.max(...waiting.map((order) => order.ordered_minutes_ago)))} ago`;
        indicator.title = waiting.map(describeOrder).join('\n');
    } else if (fulfilled.length > 0) {
        indicator.textContent = `Served ${formatMinutes(Math.min(...fulfilled.map((order) => order.fulfilled_minutes_ago ?? 0)))} ago`;
        indicator.title = fulfilled.map(describeOrder).join('\n');
    } else {
        indicator.textContent = '';
        indicator.title = '';
    }

    renderOrderProgress(element, [...waiting, ...fulfilled]);
}

/** Fills the table's completion bar with how many of the portions ordered there have been served */
function renderOrderProgress(element: HTMLElement, orders: Order[]): void {
    const progress = element.querySelector('[data-order-progress]') as HTMLElement;
    const items = orders.flatMap((order) => order.items);
    const served = items.filter((item) => item.served).length;

    progress.classList.toggle('hidden', items.length === 0);
    progress.classList.toggle('complete', items.length > 0 && served === items.length);
    progress.title = `${served} of ${pluralize(items.length, 'portion', 'portions')} served`;
    (progress.querySelector('[data-order-progress-fill]') as HTMLElement).style.width =
        items.length === 0 ? '0%' : `${(served / items.length) * 100}%`;
    setField(progress, 'order-progress', `${served}/${items.length}`);
}
