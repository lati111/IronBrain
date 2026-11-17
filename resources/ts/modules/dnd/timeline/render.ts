/** Draws a timeline: periods color the background, events are dots on the axis and timespans are bars below it */

export type ItemType = 'event' | 'timespan' | 'period';

export interface Category {
    uuid: string;
    name: string;
    description: string | null;
    color: string;
    /** Whether the category is filtered out when the timeline is opened */
    hidden_by_default: boolean;
}

export interface TimelineItem {
    uuid: string;
    category_uuid: string | null;
    name: string;
    description: string | null;
    tags: string[];
}

export interface TimelineEvent extends TimelineItem {
    year: number;
}

export interface TimelineSpan extends TimelineItem {
    start_year: number;
    end_year: number;
}

/** The settings of a timeline, each of which may be left empty */
export interface TimelineSettings {
    current_year: number | null;
    display_start_year: number | null;
    display_end_year: number | null;
}

/** A saved set of tag lists to filter and highlight a timeline by */
export interface FilterPreset {
    uuid: string;
    name: string;
    blacklist: string[];
    whitelist: string[];
    highlight: string[];
}

/** The tags the timeline is filtered and highlighted by, in lowercase */
export interface TagFilter {
    /** Items with any of these tags are hidden */
    blacklist: ReadonlySet<string>;
    /** Items without all of these tags are hidden */
    whitelist: ReadonlySet<string>;
    /** Items with any of these tags are highlighted */
    highlight: ReadonlySet<string>;
}

export interface TimelineState {
    settings: TimelineSettings;
    presets: FilterPreset[];
    categories: Category[];
    events: TimelineEvent[];
    timespans: TimelineSpan[];
    periods: TimelineSpan[];
}

/** Where the years of a drawn timeline are placed, so the page can scroll to a year */
export interface TimelineLayout {
    /** The first year of the timeline, at its left edge */
    start: number;
    /** The last year of the timeline */
    end: number;
    pixelsPerYear: number;
}

/** Called when an item is clicked */
export type SelectItemCallback = (type: ItemType, item: TimelineEvent | TimelineSpan) => void;

/** The color of items whose category was deleted */
const UNCATEGORIZED_COLOR = '#6b7280';
/** The height of a row of event labels or timespan bars */
const LANE_HEIGHT = 30;
/** The height of the row at the top holding the names of periods */
const PERIOD_ROW_HEIGHT = 24;
/** The space taken up below the axis by the year labels */
const TICK_ROW_HEIGHT = 26;
/** The space kept between event labels or timespans that share a row */
const LANE_GAP = 8;
/** The space between a timespan and its name, when the name doesn't fit inside it */
const TIMESPAN_LABEL_GAP = 4;
/** The space to aim for between year labels */
const TICK_SPACING = 110;

/** Measures text in the font event labels use, so they can be placed without overlapping */
let measureContext: CanvasRenderingContext2D | null = null;

function measureLabel(text: string): number {
    if (measureContext === null) {
        measureContext = document.createElement('canvas').getContext('2d');
    }
    if (measureContext === null) {
        return text.length * 7;
    }

    measureContext.font = `${0.8 * parseFloat(getComputedStyle(document.documentElement).fontSize)}px ${getComputedStyle(document.body).fontFamily}`;
    // The padding and borders of the label
    return measureContext.measureText(text).width + 18;
}

/** A stretch of pixels to fit into a row, along with the category it belongs to */
interface LaneRange {
    left: number;
    right: number;
    group: string;
}

/**
 * Places each range in a row where it doesn't overlap what is already there. Of those rows, it prefers one where the
 * last range has the same category, so ranges of a category line up without taking up more rows
 * @param ranges The ranges in pixels, in the order they are placed
 * @return The row of each range, and the amount of rows used
 */
function assignLanes(ranges: LaneRange[]): {lanes: number[], count: number} {
    const laneEnds: {right: number, group: string}[] = [];
    const lanes = ranges.map(range => {
        const fits = (end: {right: number}) => end.right + LANE_GAP <= range.left;
        let lane = laneEnds.findIndex(end => fits(end) && end.group === range.group);
        if (lane === -1) {
            lane = laneEnds.findIndex(fits);
        }

        if (lane === -1) {
            lane = laneEnds.length;
            laneEnds.push({right: range.right, group: range.group});
        } else {
            laneEnds[lane] = {right: range.right, group: range.group};
        }
        return lane;
    });

    return {lanes, count: laneEnds.length};
}

/**
 * Gives each category rows of its own, in the given order, so the ranges are sorted by category. A category only
 * takes up more than one row where its own ranges overlap
 * @param ranges The ranges in pixels, in the order they are placed within their category
 * @param groupOrder The categories in the order their rows are placed
 * @return The row of each range, and the amount of rows used
 */
function assignGroupedLanes(ranges: LaneRange[], groupOrder: string[]): {lanes: number[], count: number} {
    const lanes: number[] = new Array(ranges.length);
    const groups = [...new Set([...groupOrder, ...ranges.map(range => range.group)])];

    let count = 0;
    for (const group of groups) {
        const indices = ranges.flatMap((range, index) => range.group === group ? [index] : []);
        const grouped = assignLanes(indices.map(index => ranges[index]));
        indices.forEach((index, i) => lanes[index] = count + grouped.lanes[i]);
        count += grouped.count;
    }

    return {lanes, count};
}

/**
 * Picks the step between year labels, as 1, 2 or 5 times a power of ten, so the labels are about TICK_SPACING apart
 * @param pixelsPerYear How many pixels a year takes up
 */
function getTickStep(pixelsPerYear: number): number {
    const rough = TICK_SPACING / pixelsPerYear;
    const magnitude = Math.pow(10, Math.floor(Math.log10(Math.max(rough, 1))));
    for (const multiplier of [1, 2, 5, 10]) {
        if (multiplier * magnitude >= rough) {
            return multiplier * magnitude;
        }
    }
    return 10 * magnitude;
}

/**
 * Picks black or white text, whichever reads better on the given background
 * @param hex The background as #RRGGBB
 */
function getTextColor(hex: string): string {
    const r = parseInt(hex.slice(1, 3), 16);
    const g = parseInt(hex.slice(3, 5), 16);
    const b = parseInt(hex.slice(5, 7), 16);
    return (0.299 * r + 0.587 * g + 0.114 * b) > 150 ? '#111827' : '#ffffff';
}

/**
 * Describes when an item takes place, for its tooltip
 * @param type The type of the item
 * @param item The item
 */
export function formatYears(type: ItemType, item: TimelineEvent | TimelineSpan): string {
    if (type === 'event') {
        return `Year ${(item as TimelineEvent).year}`;
    }

    const span = item as TimelineSpan;
    return span.start_year === span.end_year ? `Year ${span.start_year}` : `Years ${span.start_year} to ${span.end_year}`;
}

function getTooltip(type: ItemType, item: TimelineEvent | TimelineSpan): string {
    const lines = [item.name, formatYears(type, item)];
    if (item.description) {
        lines.push(item.description);
    }
    if (item.tags.length > 0) {
        lines.push('Tags: ' + item.tags.join(', '));
    }
    return lines.join('\n');
}

function createElement(cls: string, style: Partial<CSSStyleDeclaration>, text: string | null = null): HTMLDivElement {
    const element = document.createElement('div');
    element.className = cls;
    Object.assign(element.style, style);
    if (text !== null) {
        element.textContent = text;
    }
    return element;
}

/**
 * Gets the years the timeline should show, with some room on either side of its items and of the years in its
 * settings, so the current year and the display range always fit
 * @param state The timeline state
 * @return The first and last year, or null when there is nothing to show
 */
function getYearRange(state: TimelineState): {start: number, end: number} | null {
    const {current_year, display_start_year, display_end_year} = state.settings;
    const years: number[] = [
        ...state.events.map(event => event.year),
        ...[...state.timespans, ...state.periods].flatMap(span => [span.start_year, span.end_year]),
        ...[current_year, display_start_year, display_end_year].filter((year): year is number => year !== null),
    ];
    if (years.length === 0) {
        return null;
    }

    const min = Math.min(...years);
    const max = Math.max(...years);
    const padding = Math.max(Math.ceil((max - min) * 0.05), 1);
    return {start: min - padding, end: max + padding};
}

/**
 * Draws the timeline into the track
 * @param track The element to draw into, inside a scrollable container
 * @param state The timeline state
 * @param zoom How far zoomed in, where 1 fits the whole timeline in the visible width
 * @param hiddenCategories The uuids of the categories that are filtered out. Items without a category are always shown
 * @param tagFilter The tags to hide, require and highlight items by
 * @param onSelect Called when an item is clicked
 * @return Where the years were placed, or null when there was nothing to draw
 */
export function renderTimeline(track: HTMLElement, state: TimelineState, zoom: number, hiddenCategories: ReadonlySet<string>, tagFilter: TagFilter, onSelect: SelectItemCallback): TimelineLayout | null {
    track.innerHTML = '';

    const range = getYearRange(state);
    if (range === null) {
        return null;
    }

    const isShown = (item: TimelineItem) => {
        if (hiddenCategories.has(item.category_uuid ?? '')) return false;

        const tags = item.tags.map(tag => tag.toLowerCase());
        if (tags.some(tag => tagFilter.blacklist.has(tag))) return false;
        return [...tagFilter.whitelist].every(required => tags.includes(required));
    };
    const isHighlighted = (item: TimelineItem) => item.tags.some(tag => tagFilter.highlight.has(tag.toLowerCase()));
    const shownEvents = state.events.filter(isShown);
    const shownTimespans = state.timespans.filter(isShown);
    const shownPeriods = state.periods.filter(isShown);

    const colors = new Map(state.categories.map(category => [category.uuid, category.color]));
    const getColor = (item: TimelineItem) => colors.get(item.category_uuid ?? '') ?? UNCATEGORIZED_COLOR;

    const visibleWidth = (track.parentElement?.clientWidth ?? 800) - 2;
    const pixelsPerYear = (visibleWidth / (range.end - range.start)) * zoom;
    const width = Math.max(visibleWidth, (range.end - range.start) * pixelsPerYear);
    const x = (year: number) => (year - range.start) * pixelsPerYear;

    //| Lay out the rows, with event labels above the axis and timespans below it
    const events = shownEvents.map(event => {
        const left = x(event.year) - 6;
        return {event, left, right: left + measureLabel(event.name), group: event.category_uuid ?? ''};
    });
    const eventLanes = assignLanes(events);

    // A name too long for its bar is placed right after it instead, taking up room in its row so nothing covers it
    const timespans = shownTimespans.map(timespan => {
        const left = x(timespan.start_year);
        const barRight = Math.max(x(timespan.end_year), left + 6);
        const labelWidth = measureLabel(timespan.name);
        const fits = labelWidth <= barRight - left;
        const right = fits ? barRight : barRight + TIMESPAN_LABEL_GAP + labelWidth;
        return {timespan, left, barRight, fits, right, group: timespan.category_uuid ?? ''};
    });
    // Timespans are sorted into rows per category, with those whose category was deleted last
    const timespanLanes = assignGroupedLanes(timespans, [...state.categories.map(category => category.uuid), '']);

    const axisY = PERIOD_ROW_HEIGHT + Math.max(eventLanes.count, 1) * LANE_HEIGHT + 10;
    const timespanTop = axisY + TICK_ROW_HEIGHT;
    const height = timespanTop + timespanLanes.count * LANE_HEIGHT + 10;

    // Labels near the end may reach past the last year
    track.style.width = `${Math.max(width, ...[...events, ...timespans].map(item => item.right + 4))}px`;
    track.style.height = `${height}px`;

    //| Periods
    for (const period of shownPeriods) {
        const color = getColor(period);
        const element = createElement('timeline-period', {
            left: `${x(period.start_year)}px`,
            width: `${Math.max(x(period.end_year) - x(period.start_year), 2)}px`,
            backgroundColor: color + '2e',
            borderColor: color + '80',
        });
        // Only the name opens the period, so clicks on the rest of its wide band don't get in the way
        const label = createElement('timeline-period-label', {color}, period.name);
        label.title = getTooltip('period', period);
        if (isHighlighted(period)) label.classList.add('timeline-highlight');
        label.addEventListener('click', () => onSelect('period', period));
        element.appendChild(label);
        track.appendChild(element);
    }

    //| Axis, with a label every few years
    track.appendChild(createElement('timeline-axis', {top: `${axisY - 1}px`}));
    const step = getTickStep(pixelsPerYear);
    for (let year = Math.ceil(range.start / step) * step; year <= range.end; year += step) {
        track.appendChild(createElement('timeline-tick', {left: `${x(year)}px`, top: `${axisY}px`}));
        track.appendChild(createElement('timeline-tick-label', {left: `${x(year)}px`, top: `${axisY + 9}px`}, String(year)));
    }

    //| The year the story is at now
    const currentYear = state.settings.current_year;
    if (currentYear !== null) {
        const now = createElement('timeline-now', {left: `${x(currentYear)}px`});
        now.appendChild(createElement('timeline-now-label', {}, `Now: ${currentYear}`));
        track.appendChild(now);
    }

    //| Events
    events.forEach(({event, left}, index) => {
        const color = getColor(event);
        const tooltip = getTooltip('event', event);
        const labelTop = axisY - (eventLanes.lanes[index] + 1) * LANE_HEIGHT;

        track.appendChild(createElement('timeline-event-stem', {
            left: `${x(event.year)}px`,
            top: `${labelTop + 20}px`,
            height: `${axisY - labelTop - 20}px`,
        }));

        const label = createElement('timeline-event-label', {left: `${left}px`, top: `${labelTop}px`, borderLeftColor: color}, event.name);
        const dot = createElement('timeline-event-dot', {left: `${x(event.year)}px`, top: `${axisY}px`, backgroundColor: color});
        for (const element of [label, dot]) {
            element.title = tooltip;
            if (isHighlighted(event)) element.classList.add('timeline-highlight');
            element.addEventListener('click', () => onSelect('event', event));
            track.appendChild(element);
        }
    });

    //| Timespans
    timespans.forEach(({timespan, left, barRight, fits}, index) => {
        const color = getColor(timespan);
        const top = timespanTop + timespanLanes.lanes[index] * LANE_HEIGHT;
        const bar = createElement('timeline-timespan', {
            left: `${left}px`,
            width: `${barRight - left}px`,
            top: `${top}px`,
            height: `${LANE_HEIGHT - 8}px`,
            backgroundColor: color,
            color: getTextColor(color),
        }, fits ? timespan.name : null);
        const elements = [bar];

        if (!fits) {
            elements.push(createElement('timeline-timespan-label', {
                left: `${barRight + TIMESPAN_LABEL_GAP}px`,
                top: `${top}px`,
                height: `${LANE_HEIGHT - 8}px`,
            }, timespan.name));
        }

        for (const element of elements) {
            element.title = getTooltip('timespan', timespan);
            if (isHighlighted(timespan)) element.classList.add('timeline-highlight');
            element.addEventListener('click', () => onSelect('timespan', timespan));
            track.appendChild(element);
        }
    });

    return {start: range.start, end: range.end, pixelsPerYear};
}

/**
 * Lists the categories with their colors. Clicking one filters it out of the timeline, or brings it back
 * @param legend The element to list them in
 * @param categories The categories
 * @param hiddenCategories The uuids of the categories that are filtered out
 * @param onToggle Called when a category is clicked
 */
export function renderLegend(legend: HTMLElement, categories: Category[], hiddenCategories: ReadonlySet<string>, onToggle: (uuid: string) => void): void {
    legend.innerHTML = '';
    for (const category of categories) {
        const hidden = hiddenCategories.has(category.uuid);

        const entry = document.createElement('button');
        entry.type = 'button';
        entry.className = 'timeline-legend-entry' + (hidden ? ' timeline-legend-entry-hidden' : '');
        entry.title = (category.description ? category.description + '\n' : '') + (hidden ? 'Click to show' : 'Click to hide');
        entry.setAttribute('aria-pressed', String(!hidden));
        entry.addEventListener('click', () => onToggle(category.uuid));

        const swatch = document.createElement('span');
        swatch.className = 'timeline-legend-swatch';
        swatch.style.backgroundColor = category.color;
        entry.append(swatch, category.name);
        legend.appendChild(entry);
    }
}
