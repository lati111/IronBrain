import {setupBoostTarget} from "./drag";
import {createNote, createProgressBar, createSpan, formatMinutes} from "./elements";
import {closeMealMenu, onRightClick, toggleMenu} from "./menu";
import {openPrioritizeMenu} from "./prioritize";
import {getServiceState, isPlayer, post, postParameters} from "./state";
import {KitchenEquipment, WorkJob} from "./types";

/** The equipment other than cookers, with how many pieces are clean, and cleaning the dirty ones */

/** Below these shares of clean pieces, equipment is shown as running low or as nearly out */
const LOW_CLEAN_SHARE = 0.25;
const CRITICAL_CLEAN_SHARE = 0.05;

export function renderEquipment(): void {
    const list = document.getElementById('kitchen-equipment') as HTMLElement;
    const equipment = getServiceState()!.equipment;
    list.replaceChildren(...equipment.map(createEquipmentRow));

    if (equipment.length === 0) {
        list.appendChild(createNote('No other equipment'));
    }

    const anyToClean = equipment.some((piece) => getUnqueuedDirty(piece) > 0);
    const cleanAll = document.getElementById('kitchen-clean-all') as HTMLButtonElement | null;
    if (cleanAll !== null) cleanAll.disabled = !anyToClean;
}

function createEquipmentRow(piece: KitchenEquipment): HTMLElement {
    const row = document.createElement('li');
    row.className = 'kitchen-equipment-row';
    row.title = `${piece.category_label} — ${describeUsage(piece)}`;
    setupBoostTarget(row, 'equipment', {equipment_uuid: piece.uuid});

    // Warns when few pieces are left clean, as dirty or in use pieces can't be used
    if (piece.amount > 0) {
        const share = piece.clean / piece.amount;
        row.classList.toggle('critical', share < CRITICAL_CLEAN_SHARE);
        row.classList.toggle('low', share >= CRITICAL_CLEAN_SHARE && share < LOW_CLEAN_SHARE);
    }

    // Pieces queued for cleaning, with how long until the last of them is clean
    const queue = getCleaningJobs(piece);
    const cleaning = createSpan('', 'kitchen-equipment-cleaning');
    if (queue.length > 0) {
        const minutes = queue[queue.length - 1].minutes_left;
        const amount = sumJobs(queue, (job) => job.amount);
        cleaning.textContent = minutes !== null ? `${amount} · ${formatMinutes(minutes)}` : String(amount);
    }

    const counts = document.createElement('span');
    counts.className = 'kitchen-equipment-counts';
    counts.append(
        createCount(piece.clean, 'clean'),
        createCount(piece.in_use, 'in use'),
        createCount(piece.dirty, 'dirty'),
    );

    row.append(createSpan(piece.name, 'truncate'), cleaning, counts);
    if (queue.length > 0) {
        const work = sumJobs(queue, (job) => job.work);
        const done = sumJobs(queue, (job) => job.work * job.progress);
        row.appendChild(createProgressBar(work > 0 ? done / work : 0));
    }

    if (!isPlayer()) {
        row.addEventListener('click', () => toggleEquipmentMenu(piece, row));
        if (queue.length > 0) {
            // Prioritising one piece moves every piece of the equipment, so the first stands for all
            onRightClick(row, () => openPrioritizeMenu(row, `${piece.name}: cleaning`, [queue[0].uuid]));
        }
    }

    return row;
}

function getCleaningJobs(piece: KitchenEquipment): WorkJob[] {
    return getServiceState()!.stations.cleaning.jobs.filter((job) => job.equipment_uuid === piece.uuid);
}

function sumJobs(jobs: WorkJob[], value: (job: WorkJob) => number): number {
    return jobs.reduce((total, job) => total + value(job), 0);
}

/** How many dirty pieces aren't queued for cleaning yet */
function getUnqueuedDirty(piece: KitchenEquipment): number {
    const queued = sumJobs(getCleaningJobs(piece), (job) => job.amount);
    return Math.max(0, piece.dirty - queued);
}

function createCount(amount: number, label: string): HTMLElement {
    const count = document.createElement('span');
    count.dataset.count = label;
    count.textContent = String(amount);
    count.title = `${amount} ${label}`;
    return count;
}

function describeUsage(piece: KitchenEquipment): string {
    return `${piece.clean} clean, ${piece.in_use} in use, ${piece.dirty} dirty`;
}

/** Opens the menu for a piece of equipment, where any number of its dirty pieces can be cleaned at once */
function toggleEquipmentMenu(piece: KitchenEquipment, anchor: HTMLElement): void {
    toggleMenu(anchor, `${piece.name}: ${describeUsage(piece)}`, (_option, options) => {
        const available = getUnqueuedDirty(piece);
        if (available === 0) {
            options.appendChild(createNote('Nothing to clean'));
            return;
        }

        const form = document.createElement('form');
        form.className = 'meal-menu-clean';

        const input = document.createElement('input');
        input.type = 'number';
        input.min = '1';
        input.max = String(available);
        input.value = String(available);
        input.required = true;
        input.className = 'underlined w-14';

        const button = document.createElement('button');
        button.type = 'submit';
        button.className = 'meal-menu-option';
        button.textContent = 'Clean';

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            if (!form.reportValidity()) return;

            closeMealMenu();
            postParameters(`/equipment/${piece.uuid}/clean`, {amount: input.value});
        });

        form.append(input, button);
        options.appendChild(form);
        input.focus();
        input.select();
    });
}

async function cleanAllEquipment(): Promise<void> {
    await post('/equipment/clean-all');
}

(<any>window).cleanAllEquipment = cleanAllEquipment;
