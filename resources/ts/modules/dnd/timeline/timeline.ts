import {getData, postData} from "../../../main";
import {openModal, init as initModals, closeModal} from "../../../components/modal";
import {
    Category,
    ItemType,
    TagFilter,
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
    presets: [],
    categories: [], events: [], timespans: [], periods: [],
};
/** The uuids of the categories that are filtered out. Clicking a category in the legend changes this */
let hiddenCategories = new Set<string>();
/** The tags the timeline is filtered and highlighted by, which the tag modal changes */
let tagFilter: TagFilter = {blacklist: new Set(), whitelist: new Set(), highlight: new Set()};
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

    // The tag field of items and the three tag lists of the filter
    attachTagAutocomplete(getField('tags'));
    for (const list of TAG_LISTS) {
        attachTagAutocomplete(getTagInput(list));
    }

    const response = await getData(apiUrl);
    setState(response.data.timeline);
    applyDefaultFilter();
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
    // Categories that were deleted can't be filtered out anymore
    hiddenCategories = new Set([...hiddenCategories].filter(uuid => state.categories.some(category => category.uuid === uuid)));
    drawLegend();
    fillCategoryOptions();
    renderCategoryList();
    fillPresetSelect();
    render();
}

function drawLegend(): void {
    renderLegend(document.getElementById('timeline-legend')!, state.categories, hiddenCategories, toggleCategory);
}

/** Filters out the categories that are hidden by default, which is only done when the timeline is opened */
function applyDefaultFilter(): void {
    hiddenCategories = new Set(state.categories.filter(category => category.hidden_by_default).map(category => category.uuid));
    drawLegend();
    render();
}

/** Filters a category out of the timeline, or brings it back */
function toggleCategory(uuid: string): void {
    if (!hiddenCategories.delete(uuid)) {
        hiddenCategories.add(uuid);
    }

    drawLegend();
    render();
}

function render(): void {
    const scroll = document.getElementById('timeline-scroll')!;
    const track = document.getElementById('timeline-track')!;

    // Shown before drawing, as the timeline is sized to the visible width, which is zero while hidden
    scroll.classList.remove('hidden');
    layout = renderTimeline(track, state, zoom, hiddenCategories, tagFilter, openItemModal);
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

//| Tag autocomplete

interface TagSuggestion {
    tag: string;
    /** How many items have the tag */
    count: number;
}

/** Counts how many items have each tag, ignoring case and keeping the spelling that was seen first */
function getTagCounts(): TagSuggestion[] {
    const counts = new Map<string, TagSuggestion>();
    for (const item of [...state.events, ...state.timespans, ...state.periods]) {
        for (const tag of item.tags) {
            const key = tag.toLowerCase();
            const suggestion = counts.get(key) ?? {tag, count: 0};
            suggestion.count++;
            counts.set(key, suggestion);
        }
    }

    return [...counts.values()];
}

/**
 * Ranks how closely a tag matches what was typed, where lower is closer: a prefix, then somewhere within the tag, then
 * with the typed letters in order but spread out. Everything matches when nothing was typed yet
 * @param tag The tag
 * @param query What was typed, in lowercase
 * @return The rank, or null when the tag doesn't match
 */
function rankTagMatch(tag: string, query: string): number | null {
    if (query === '') return 0;

    const lower = tag.toLowerCase();
    if (lower.startsWith(query)) return 0;
    if (lower.includes(query)) return 1;

    let position = 0;
    for (const letter of lower) {
        if (letter === query[position]) position++;
    }
    return position === query.length ? 2 : null;
}

/**
 * Gets the tags to suggest for what is being typed, closest match first and then those on the most items
 * @param query What was typed, in lowercase
 * @param entered The tags already in the field, in lowercase, which aren't suggested again
 */
function getTagSuggestions(query: string, entered: Set<string>): TagSuggestion[] {
    return getTagCounts()
        .flatMap(suggestion => {
            const lower = suggestion.tag.toLowerCase();
            // A tag that has been typed out in full has nothing left to complete
            const rank = entered.has(lower) || lower === query ? null : rankTagMatch(suggestion.tag, query);
            return rank === null ? [] : [{suggestion, rank}];
        })
        .sort((a, b) => a.rank - b.rank || b.suggestion.count - a.suggestion.count || a.suggestion.tag.localeCompare(b.suggestion.tag))
        .map(({suggestion}) => suggestion)
        .slice(0, 8);
}

/**
 * Suggests the tags of the timeline while typing in a field with comma separated tags. Tab picks the top suggestion,
 * and the arrow keys and enter pick another one
 * @param input The field to add suggestions to
 */
function attachTagAutocomplete(input: HTMLInputElement): void {
    const list = document.createElement('div');
    list.className = 'timeline-autocomplete hidden';
    list.setAttribute('role', 'listbox');
    input.parentElement!.classList.add('relative');
    input.parentElement!.appendChild(list);
    input.autocomplete = 'off';

    let suggestions: TagSuggestion[] = [];
    let active = -1;

    /** Finds the tag being typed, which runs from the comma before the caret to the next comma */
    const getSegment = () => {
        const caret = input.selectionStart ?? input.value.length;
        const start = input.value.lastIndexOf(',', caret - 1) + 1;
        const comma = input.value.indexOf(',', caret);
        return {start, caret, end: comma === -1 ? input.value.length : comma};
    };

    const hide = () => {
        list.classList.add('hidden');
        suggestions = [];
        active = -1;
    };

    const highlight = () => {
        [...list.children].forEach((child, index) => child.classList.toggle('timeline-autocomplete-active', index === active));
        list.children[active]?.scrollIntoView({block: 'nearest'});
    };

    const accept = (suggestion: TagSuggestion) => {
        const {start, end} = getSegment();
        const atEnd = end === input.value.length;
        const insert = (start > 0 ? ' ' : '') + suggestion.tag + (atEnd ? ', ' : '');

        input.value = input.value.slice(0, start) + insert + input.value.slice(end);
        const caret = start + insert.length;
        input.setSelectionRange(caret, caret);
        hide();
    };

    const refresh = () => {
        const {start, caret, end} = getSegment();
        const query = input.value.slice(start, caret).trim().toLowerCase();
        const others = (input.value.slice(0, start) + ',' + input.value.slice(end)).split(',').map(tag => tag.trim().toLowerCase());

        suggestions = getTagSuggestions(query, new Set(others));
        active = -1;
        list.innerHTML = '';
        if (suggestions.length === 0) {
            list.classList.add('hidden');
            return;
        }

        for (const suggestion of suggestions) {
            const option = document.createElement('button');
            option.type = 'button';
            option.className = 'timeline-autocomplete-option';
            option.setAttribute('role', 'option');
            option.tabIndex = -1;
            option.append(suggestion.tag);

            const count = document.createElement('span');
            count.className = 'timeline-autocomplete-count';
            count.textContent = String(suggestion.count);
            option.appendChild(count);

            // Pressing down rather than clicking, as that happens before the field loses focus and hides the list
            option.addEventListener('mousedown', event => {
                event.preventDefault();
                accept(suggestion);
            });
            list.appendChild(option);
        }
        list.classList.remove('hidden');
    };

    input.addEventListener('input', refresh);
    input.addEventListener('focus', refresh);
    input.addEventListener('click', refresh);
    input.addEventListener('blur', hide);
    input.addEventListener('keydown', event => {
        if (suggestions.length === 0) return;

        switch (event.key) {
            case 'ArrowDown':
            case 'ArrowUp': {
                event.preventDefault();
                const step = event.key === 'ArrowDown' ? 1 : -1;
                active = active === -1 ? (step === 1 ? 0 : suggestions.length - 1) : (active + step + suggestions.length) % suggestions.length;
                highlight();
                break;
            }
            case 'Enter':
                // Only when one was picked, so enter still submits the form otherwise
                if (active !== -1) {
                    event.preventDefault();
                    accept(suggestions[active]);
                }
                break;
            case 'Tab':
                if (!event.shiftKey) {
                    event.preventDefault();
                    accept(suggestions[Math.max(active, 0)]);
                }
                break;
            case 'Escape':
                event.stopPropagation();
                hide();
                break;
        }
    });
}

//| Tag filter modal

const TAG_LISTS = ['blacklist', 'whitelist', 'highlight'] as const;
type TagList = typeof TAG_LISTS[number];

function getTagsForm(): HTMLFormElement {
    return document.getElementById('timeline-tags-form') as HTMLFormElement;
}

function getTagInput(list: TagList): HTMLInputElement {
    return getTagsForm().querySelector(`[name="${list}"]`) as HTMLInputElement;
}

function getPresetSelect(): HTMLSelectElement {
    return document.getElementById('timeline-preset-select') as HTMLSelectElement;
}

function getPresetNameInput(): HTMLInputElement {
    return document.getElementById('timeline-preset-name') as HTMLInputElement;
}

/**
 * Splits a comma separated list of tags, without surrounding whitespace, empty entries or duplicates
 * @param text The comma separated list
 */
function parseTags(text: string): string[] {
    const seen = new Set<string>();
    return text.split(',').map(tag => tag.trim()).filter(tag => {
        const key = tag.toLowerCase();
        if (tag === '' || seen.has(key)) return false;
        seen.add(key);
        return true;
    });
}

function openTagModal(): void {
    openModal('timeline-tags-modal');
    getTagInput('blacklist').focus();
}

/** Filters and highlights the timeline by the tags in the modal */
function applyTagFilter(close: boolean = true): void {
    const [blacklist, whitelist, highlight] = TAG_LISTS.map(list =>
        new Set(parseTags(getTagInput(list).value).map(tag => tag.toLowerCase())));
    tagFilter = {blacklist, whitelist, highlight};

    // Shows whether the timeline is being filtered, as the modal is closed most of the time
    const active = blacklist.size + whitelist.size + highlight.size > 0;
    document.getElementById('timeline-tags-button')!.textContent = active ? 'Tags ●' : 'Tags';

    render();
    if (close) {
        closeModal('timeline-tags-modal');
    }
}

function clearTagFilter(): void {
    for (const list of TAG_LISTS) {
        getTagInput(list).value = '';
    }
    applyTagFilter(false);
}

function fillPresetSelect(): void {
    const select = getPresetSelect();
    const current = select.value;

    select.innerHTML = '';
    select.add(new Option(state.presets.length === 0 ? 'No saved presets' : 'Choose a preset...', ''));
    for (const preset of state.presets) {
        select.add(new Option(preset.name, preset.uuid));
    }

    select.value = state.presets.some(preset => preset.uuid === current) ? current : '';
}

/** Suggests the name of the chosen preset for saving, so saving again replaces it */
function selectPreset(): void {
    const preset = state.presets.find(preset => preset.uuid === getPresetSelect().value);
    if (preset !== undefined) {
        getPresetNameInput().value = preset.name;
    }
}

/** Fills the tag lists with those of the chosen preset and filters by them */
function loadPreset(): void {
    const preset = state.presets.find(preset => preset.uuid === getPresetSelect().value);
    if (preset === undefined) return;

    for (const list of TAG_LISTS) {
        getTagInput(list).value = preset[list].join(', ');
    }
    applyTagFilter(false);
}

async function savePreset(): Promise<void> {
    const nameInput = getPresetNameInput();
    const name = nameInput.value.trim();
    if (name === '') {
        nameInput.focus();
        return;
    }

    const data = new FormData();
    data.set('name', name);
    for (const list of TAG_LISTS) {
        data.set(list, parseTags(getTagInput(list).value).join(','));
    }

    // A preset of the same name is replaced, so make sure that is meant
    const existing = state.presets.find(preset => preset.name.toLowerCase() === name.toLowerCase());
    if (existing !== undefined && !window.confirm(`Replace the preset "${existing.name}"?`)) return;

    const result = await post('preset/save', data);
    if (result === null) return;

    setState(result.timeline);
    getPresetSelect().value = result.uuid;
}

async function deletePreset(): Promise<void> {
    const preset = state.presets.find(preset => preset.uuid === getPresetSelect().value);
    if (preset === undefined || !window.confirm(`Delete the preset "${preset.name}"?`)) return;

    const result = await post(`preset/${preset.uuid}/delete`, new FormData());
    if (result === null) return;

    getPresetNameInput().value = '';
    setState(result.timeline);
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

        const bottom = document.createElement('div');
        bottom.className = 'flex gap-2 items-center justify-between';

        const hidden = document.createElement('label');
        hidden.className = 'flex gap-2 items-center text-sm';
        hidden.title = 'Filters the category out when the timeline is opened. It can still be shown by clicking it';
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'checkbox';
        hiddenInput.name = 'hidden_by_default';
        hiddenInput.value = '1';
        hiddenInput.checked = category.hidden_by_default;
        hidden.append(hiddenInput, 'Hidden by default');

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'cancel_interactive px-3';
        remove.textContent = 'Delete';
        remove.addEventListener('click', () => void deleteCategory(category));
        bottom.append(hidden, remove);

        row.append(top, description, bottom);
        list.appendChild(row);
    }
}

async function deleteCategory(category: Category): Promise<void> {
    const items = [...state.events, ...state.timespans, ...state.periods].filter(item => item.category_uuid === category.uuid).length;
    const consequence = items === 0 ? '' : ` Its ${items} ${items === 1 ? 'item stays' : 'items stay'} on the timeline without a category.`;
    if (!window.confirm(`Delete the category "${category.name}"?${consequence}`)) return;

    const result = await post(`category/${category.uuid}/delete`, new FormData());
    if (result !== null) {
        setState(result.timeline);
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
(<any>window).openTagModal          = openTagModal;
(<any>window).applyTagFilter        = () => applyTagFilter();
(<any>window).clearTagFilter        = clearTagFilter;
(<any>window).selectPreset          = selectPreset;
(<any>window).loadPreset            = loadPreset;
(<any>window).savePreset            = savePreset;
(<any>window).deletePreset          = deletePreset;
(<any>window).saveSettings          = saveSettings;
(<any>window).openModal             = openModal;
(<any>window).closeModal            = closeModal;
