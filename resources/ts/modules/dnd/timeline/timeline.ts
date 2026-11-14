import {getData, postData} from "../../../main";
import {openModal, init as initModals, closeModal} from "../../../components/modal";
import {
    Category,
    ItemType,
    TimelineEvent,
    TimelineLayout,
    TimelineSpan,
    TimelineState,
    renderLegend,
    renderTimeline,
} from "./render";

/** The url of the timeline's api */
let apiUrl = '';
let state: TimelineState = {
    settings: {current_year: null, display_start_year: null, display_end_year: null},
    categories: [], events: [], timespans: [], periods: [],
};
/** Where the years were placed when the timeline was last drawn, or null when there was nothing to draw */
let layout: TimelineLayout | null = null;
/** How far zoomed in, where 1 fits the whole timeline in the visible width */
let zoom = 1;
/** The item open in the modal, or null when a new one is being added */
let editing: {type: ItemType, uuid: string} | null = null;
/** Whether a request is running, so clicking save twice doesn't save twice */
let busy = false;

const MIN_ZOOM = 1;
const MAX_ZOOM = 500;

async function init(): Promise<void> {
    apiUrl = document.getElementById('timeline')!.dataset.apiUrl!;
    initModals();

    const response = await getData(apiUrl);
    setState(response.data.timeline);
    showDisplayRange();

    // Not passive, so scrolling zooms the timeline instead of scrolling the page
    const scroll = document.getElementById('timeline-scroll')!;
    scroll.addEventListener('wheel', onTimelineWheel, {passive: false});
    initDragPanning(scroll);

    let resizeTimeout: number | undefined;
    window.addEventListener('resize', () => {
        window.clearTimeout(resizeTimeout);
        resizeTimeout = window.setTimeout(render, 150);
    });
}

//| Drawing

function setState(newState: TimelineState): void {
    state = newState;
    renderLegend(document.getElementById('timeline-legend')!, state.categories);
    fillCategoryOptions();
    renderCategoryList();
    render();
}

function render(): void {
    const scroll = document.getElementById('timeline-scroll')!;
    const track = document.getElementById('timeline-track')!;

    // Shown before drawing, as the timeline is sized to the visible width, which is zero while hidden
    scroll.classList.remove('hidden');
    layout = renderTimeline(track, state, zoom, openItemModal);
    const drawn = layout !== null;

    scroll.classList.toggle('hidden', !drawn);
    document.getElementById('timeline-empty')!.classList.toggle('hidden', drawn);
}

/**
 * Zooms in or out, keeping the year under the given point in place. Every year's position scales with the zoom, so
 * the point stays put by scaling the scroll position along with it
 * @param factor How much to zoom by, above 1 to zoom in and below 1 to zoom out
 * @param anchor Where to zoom around, in pixels from the left edge of the visible timeline. The middle when left out
 */
function zoomTimeline(factor: number, anchor: number | null = null): void {
    const scroll = document.getElementById('timeline-scroll')!;
    const offset = anchor ?? scroll.clientWidth / 2;

    const newZoom = Math.min(Math.max(zoom * factor, MIN_ZOOM), MAX_ZOOM);
    if (newZoom === zoom) return;

    const ratio = newZoom / zoom;
    const scrollLeft = (scroll.scrollLeft + offset) * ratio - offset;

    zoom = newZoom;
    render();
    scroll.scrollLeft = scrollLeft;
}

/**
 * Zooms and scrolls to the default display range of the settings. An end that is left empty is the edge of the
 * timeline, and without either the whole timeline is shown
 */
function showDisplayRange(): void {
    zoom = MIN_ZOOM;
    render();

    const {display_start_year, display_end_year} = state.settings;
    if (layout === null || (display_start_year === null && display_end_year === null)) return;

    const start = display_start_year ?? layout.start;
    const end = display_end_year ?? layout.end;
    // At the lowest zoom the whole timeline fits, so the range fits when zoomed in by how much smaller it is
    zoom = Math.min(Math.max((layout.end - layout.start) / Math.max(end - start, 1), MIN_ZOOM), MAX_ZOOM);
    render();

    if (layout !== null) {
        document.getElementById('timeline-scroll')!.scrollLeft = (start - layout.start) * layout.pixelsPerYear;
    }
}

/** The zoom built up from wheel events since the last frame, applied once per frame so fast scrolling stays smooth */
let pendingWheelZoom: {factor: number, anchor: number} | null = null;

/**
 * Zooms around the cursor when scrolling up or down over the timeline. Sideways scrolling, such as on a touchpad or
 * with shift held, still pans the timeline
 */
function onTimelineWheel(event: WheelEvent): void {
    if (event.shiftKey || Math.abs(event.deltaX) > Math.abs(event.deltaY)) return;
    event.preventDefault();

    // Lines and pages are scrolled by mouse wheels in some browsers, which are converted to rough pixel amounts
    const pixels = event.deltaY * (event.deltaMode === 1 ? 16 : event.deltaMode === 2 ? 400 : 1);
    const scroll = event.currentTarget as HTMLElement;
    const anchor = event.clientX - scroll.getBoundingClientRect().left;

    if (pendingWheelZoom === null) {
        pendingWheelZoom = {factor: 1, anchor};
        requestAnimationFrame(() => {
            const {factor, anchor} = pendingWheelZoom!;
            pendingWheelZoom = null;
            zoomTimeline(factor, anchor);
        });
    }

    pendingWheelZoom.factor *= Math.exp(-pixels * 0.002);
    pendingWheelZoom.anchor = anchor;
}

/** How far the pointer has to move before pressing down on the timeline counts as dragging rather than clicking */
const DRAG_THRESHOLD = 4;

/**
 * Pans the timeline sideways while it is dragged. A drag ends without opening what it started or ended on, while a
 * press that barely moves still counts as a click
 * @param scroll The scrollable container of the timeline
 */
function initDragPanning(scroll: HTMLElement): void {
    let drag: {pointerId: number, startX: number, startScroll: number, moved: boolean} | null = null;

    scroll.addEventListener('pointerdown', event => {
        if (event.button !== 0) return;
        drag = {pointerId: event.pointerId, startX: event.clientX, startScroll: scroll.scrollLeft, moved: false};
    });

    scroll.addEventListener('pointermove', event => {
        if (drag === null || event.pointerId !== drag.pointerId) return;

        const distance = event.clientX - drag.startX;
        if (!drag.moved && Math.abs(distance) < DRAG_THRESHOLD) return;

        if (!drag.moved) {
            drag.moved = true;
            // Keeps the drag going when the pointer leaves the timeline
            scroll.setPointerCapture(event.pointerId);
            scroll.classList.add('dragging');
        }
        scroll.scrollLeft = drag.startScroll - distance;
    });

    const endDrag = (event: PointerEvent) => {
        if (drag === null || event.pointerId !== drag.pointerId) return;

        if (drag.moved) {
            scroll.classList.remove('dragging');
            // The click fired right after the drag would open the item under the pointer. It follows straight after
            // the pointer is let go, so the blocker is dropped again before any later click when none follows
            const blockClick = (click: MouseEvent) => click.stopPropagation();
            window.addEventListener('click', blockClick, {capture: true, once: true});
            window.setTimeout(() => window.removeEventListener('click', blockClick, {capture: true}), 0);
        }
        drag = null;
    };
    scroll.addEventListener('pointerup', endDrag);
    scroll.addEventListener('pointercancel', endDrag);
}

//| Item modal

function getForm(): HTMLFormElement {
    return document.getElementById('timeline-item-form') as HTMLFormElement;
}

function getField<T extends HTMLElement = HTMLInputElement>(name: string): T {
    return getForm().querySelector(`[name="${name}"]`) as T;
}

function getType(): ItemType {
    return getField<HTMLSelectElement>('type').value as ItemType;
}

/** Lists the existing categories as suggestions for the category field */
function fillCategoryOptions(): void {
    const options = document.getElementById('timeline-category-options')!;
    options.innerHTML = '';
    for (const category of state.categories) {
        options.appendChild(new Option(category.name));
    }
}

function findCategoryByName(name: string): Category | undefined {
    const wanted = name.trim().toLowerCase();
    return state.categories.find(category => category.name.toLowerCase() === wanted);
}

/** Offers a color only while the typed category doesn't exist yet, as it is created when the item is saved */
function updateCategoryColor(): void {
    const name = (document.getElementById('timeline-item-category') as HTMLInputElement).value;
    document.getElementById('timeline-category-color')!
        .classList.toggle('hidden', name.trim() === '' || findCategoryByName(name) !== undefined);
}

/** Enables the year fields of the selected type, and disables the others so they are neither required nor sent */
function updateItemTypeFields(): void {
    const isRange = getType() !== 'event';

    for (const wrapper of getForm().querySelectorAll<HTMLElement>('[data-item-years]')) {
        const active = (wrapper.dataset.itemYears === 'range') === isRange;
        wrapper.classList.toggle('hidden', !active);
        wrapper.querySelectorAll('input').forEach(input => input.disabled = !active);
    }
}

/**
 * Opens the modal to add a new item, or to edit one when given
 * @param type The type of the item to edit
 * @param item The item to edit
 */
function openItemModal(type: ItemType | null = null, item: TimelineEvent | TimelineSpan | null = null): void {
    const form = getForm();
    form.reset();
    editing = type !== null && item !== null ? {type, uuid: item.uuid} : null;

    const typeSelect = getField<HTMLSelectElement>('type');
    typeSelect.value = type ?? 'event';
    // Items keep their type, as each type is saved apart
    typeSelect.disabled = editing !== null;
    updateItemTypeFields();

    getField('category').value = state.categories.find(category => category.uuid === item?.category_uuid)?.name ?? '';
    updateCategoryColor();

    if (item !== null) {
        getField('name').value = item.name;
        getField<HTMLTextAreaElement>('description').value = item.description ?? '';
        getField('tags').value = item.tags.join(', ');

        if (type === 'event') {
            getField('year').value = String((item as TimelineEvent).year);
        } else {
            getField('start_year').value = String((item as TimelineSpan).start_year);
            getField('end_year').value = String((item as TimelineSpan).end_year);
        }
    }

    const label = editing !== null ? `Edit ${type}` : 'Add to timeline';
    document.getElementById('timeline-item-title')!.textContent = label;
    document.getElementById('timeline-item-delete')!.classList.toggle('hidden', editing === null);

    openModal('timeline-item-modal');
    getField('name').focus();
}

/**
 * Posts to the timeline's api, showing the result
 * @param path The path within the timeline's api
 * @param data The parameters
 * @return The response data, or null when the request failed or another one is still running
 */
async function post(path: string, data: FormData): Promise<any | null> {
    if (busy) return null;

    busy = true;
    try {
        const response = await postData(`${apiUrl}/${path}`, data);
        if (!response?.ok) return null;

        response.announce();
        return response.data;
    } catch {
        // The error has already been shown to the user
        return null;
    } finally {
        busy = false;
    }
}

async function saveItem(): Promise<void> {
    const form = getForm();
    if (!form.reportValidity()) return;

    const data = new FormData(form);
    data.set('name', String(data.get('name')).trim());

    const result = editing !== null
        ? await post(`item/${editing.type}/${editing.uuid}/edit`, data)
        : await post('item/create', data);
    if (result === null) return;

    closeModal('timeline-item-modal');
    setState(result.timeline);
}

async function deleteItem(): Promise<void> {
    if (editing === null || !window.confirm(`Delete this ${editing.type}?`)) return;

    const result = await post(`item/${editing.type}/${editing.uuid}/delete`, new FormData());
    if (result === null) return;

    closeModal('timeline-item-modal');
    setState(result.timeline);
}

//| Settings modal

function getSettingsForm(): HTMLFormElement {
    return document.getElementById('timeline-settings-form') as HTMLFormElement;
}

function openSettingsModal(): void {
    const form = getSettingsForm();
    for (const [setting, value] of Object.entries(state.settings)) {
        (form.querySelector(`[name="${setting}"]`) as HTMLInputElement).value = value === null ? '' : String(value);
    }

    openModal('timeline-settings-modal');
}

async function saveSettings(): Promise<void> {
    const form = getSettingsForm();
    if (!form.reportValidity()) return;

    const result = await post('settings/edit', new FormData(form));
    if (result === null) return;

    closeModal('timeline-settings-modal');
    setState(result.timeline);
    showDisplayRange();
}

//| Categories modal

function openCategoryModal(): void {
    renderCategoryList();
    openModal('timeline-category-modal');
}

/** Lists every category with fields to edit it, each saved on its own */
function renderCategoryList(): void {
    const list = document.getElementById('timeline-category-list')!;
    list.innerHTML = '';

    if (state.categories.length === 0) {
        list.textContent = 'There are no categories yet. They are created when you add an item.';
        return;
    }

    for (const category of state.categories) {
        const row = document.createElement('form');
        row.className = 'flex flex-col gap-2 pb-3 border-b border-gray-200';
        row.onsubmit = event => {
            event.preventDefault();
            void saveCategory(category.uuid, row);
        };

        const top = document.createElement('div');
        top.className = 'flex gap-2 items-center';
        const name = document.createElement('input');
        name.name = 'name';
        name.className = 'underlined flex-1';
        name.maxLength = 64;
        name.required = true;
        name.value = category.name;
        const color = document.createElement('input');
        color.type = 'color';
        color.name = 'color';
        color.className = 'h-9 w-12';
        color.value = category.color;
        const save = document.createElement('button');
        save.type = 'submit';
        save.className = 'interactive px-3';
        save.textContent = 'Save';
        top.append(name, color, save);

        const description = document.createElement('input');
        description.name = 'description';
        description.className = 'underlined';
        description.maxLength = 2048;
        description.placeholder = 'Description...';
        description.value = category.description ?? '';

        row.append(top, description);
        list.appendChild(row);
    }
}

async function saveCategory(uuid: string, form: HTMLFormElement): Promise<void> {
    if (!form.reportValidity()) return;

    const result = await post(`category/${uuid}/edit`, new FormData(form));
    if (result !== null) {
        setState(result.timeline);
    }
}

(<any>window).init                  = init;
(<any>window).zoomTimeline          = (factor: number) => zoomTimeline(factor);
(<any>window).openItemModal         = () => openItemModal();
(<any>window).updateItemTypeFields  = updateItemTypeFields;
(<any>window).saveItem              = saveItem;
(<any>window).deleteItem            = deleteItem;
(<any>window).updateCategoryColor   = updateCategoryColor;
(<any>window).openCategoryModal     = openCategoryModal;
(<any>window).openSettingsModal     = openSettingsModal;
(<any>window).saveSettings          = saveSettings;
(<any>window).openModal             = openModal;
(<any>window).closeModal            = closeModal;
