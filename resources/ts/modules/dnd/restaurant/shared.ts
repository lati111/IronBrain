import {FetchResponse, postData} from "../../../main";

/** Helpers shared by the restaurant management and manage restaurant screens */

//| Money

/** The coins below gold, from the smallest up. Each is worth ten of the previous, and gold takes everything above */
const LOWER_COINS = ['b', 'cp', 'sp'] as const;

/**
 * Formats an amount of bits as coins, leaving out coins there are none of, so 1234 becomes "1gp 2sp 3cp 4b" and 50
 * becomes "5cp". Fractions of a bit stay on the bits
 * @param bits The amount in bits
 */
/** Where the ingredients that expired as a service ended and the day moved on are kept, for the management page to
 * show once it has loaded */
export const EXPIRED_STORAGE_KEY = 'dnd-restaurant-expired';

/**
 * Shows how satisfied customers were, from 1 to 5, such as "★ 4.5", or a dash when nothing was rated
 * @param rating The rating, or null when nothing was rated
 */
export function formatSatisfaction(rating: number | null): string {
    return rating === null ? '—' : `★ ${rating.toFixed(1).replace(/\.0$/, '')}`;
}

export function formatMoney(bits: number): string {
    const sign = bits < 0 ? '-' : '';
    const rounded = Math.round(Math.abs(bits) * 100) / 100;
    let whole = Math.floor(rounded);
    const fraction = rounded - whole;

    const parts: string[] = [];
    LOWER_COINS.forEach((coin, index) => {
        let amount = whole % 10;
        if (index === 0) {
            amount = parseFloat((amount + fraction).toFixed(2));
        }

        if (amount > 0) {
            parts.unshift(`${amount}${coin}`);
        }
        whole = Math.floor(whole / 10);
    });

    if (whole > 0) {
        parts.unshift(`${whole}gp`);
    }

    return parts.length === 0 ? '0b' : sign + parts.join(' ');
}

/** Refreshes the coin display of each money input, keyed by input */
const moneyInputDisplays = new WeakMap<HTMLInputElement, () => void>();

/**
 * Shows the value of a number input as coins while it isn't being edited. This is only visual, as the coins are laid
 * over the input while its own text is hidden, so the input keeps its raw value
 * @param input The input holding an amount in bits
 */
export function setupMoneyInput(input: HTMLInputElement): void {
    const wrapper = document.createElement('div');
    wrapper.className = 'money-input-wrapper';
    input.replaceWith(wrapper);
    input.classList.add('money-input');

    const display = document.createElement('span');
    display.className = 'money-input-display';
    wrapper.append(input, display);

    const refresh = () => {
        const value = parseFloat(input.value);
        display.textContent = isNaN(value) ? '' : formatMoney(value);
        display.style.paddingLeft = getComputedStyle(input).paddingLeft;
    };

    input.addEventListener('input', refresh);
    input.addEventListener('blur', refresh);
    // Resetting happens after the event, so the display follows once it is done
    input.form?.addEventListener('reset', () => setTimeout(refresh));
    moneyInputDisplays.set(input, refresh);
    refresh();
}

/**
 * Refreshes the coin display of the money inputs within an element, for after their values are set from code
 * @param root The element containing the inputs
 */
export function refreshMoneyInputs(root: ParentNode): void {
    root.querySelectorAll<HTMLInputElement>('input.money-input').forEach((input) => moneyInputDisplays.get(input)?.());
}

//| Fields

export function setField(root: HTMLElement, field: string, value: string): void {
    const element = root.querySelector(`[data-field="${field}"]`);
    if (element !== null) {
        element.textContent = value;
    }
}

/** Where the sidebar's collapsed state is remembered, which pages can set to keep their own */
let sidebarStorageKey = 'dnd-restaurant-sidebar-collapsed';

export function toggleSidebar(): void {
    const sidebar = document.getElementById('inventory-sidebar')!;
    sidebar.classList.toggle('collapsed');
    localStorage.setItem(sidebarStorageKey, sidebar.classList.contains('collapsed') ? '1' : '0');
}

/**
 * Restores the collapsed state of the sidebar from the previous visit
 * @param storageKey Where the state is remembered, for pages that keep their own
 * @param collapsedByDefault Whether the sidebar starts collapsed until it has been opened or closed
 */
export function restoreSidebar(storageKey: string = sidebarStorageKey, collapsedByDefault: boolean = false): void {
    sidebarStorageKey = storageKey;

    // Applied both ways, as pages that start collapsed render the sidebar collapsed to avoid it flashing open
    const saved = localStorage.getItem(storageKey);
    const collapsed = saved === '1' || (saved === null && collapsedByDefault);
    document.getElementById('inventory-sidebar')!.classList.toggle('collapsed', collapsed);
}

//| Requests

/** How long after a request finishes that the same request is still taken as an accidental repeat */
const REPEAT_COOLDOWN = 400;

/** The requests that are running or have only just finished, by their url and body */
const recentRequests = new Set<string>();

/** Describes a request by its url and body, so repeats of the same request can be recognised */
function describeRequest(url: string, data: FormData | string): string {
    if (typeof data === 'string') {
        return `${url}\n${data}`;
    }

    const entries = [...data.entries()].map(([key, value]) =>
        `${key}=${value instanceof File ? `${value.name}:${value.size}` : value}`);
    return `${url}\n${entries.join('&')}`;
}

/**
 * Posts like postData, but ignores the request when the same one is still running or has only just finished, so
 * clicking something twice in quick succession doesn't do it twice
 * @param url The url to post to
 * @param data The parameters, as form data or json
 * @return The response, or undefined when the request was a repeat and wasn't sent
 */
export async function postOnce(url: string, data: FormData | string = new FormData()): Promise<FetchResponse | undefined> {
    const key = describeRequest(url, data);
    if (recentRequests.has(key)) return undefined;

    recentRequests.add(key);
    try {
        return await postData(url, data);
    } finally {
        setTimeout(() => recentRequests.delete(key), REPEAT_COOLDOWN);
    }
}

/**
 * Posts a form, announcing the result
 * @param form The form to post
 * @param url The url to post to
 * @param fileFields File inputs that are left out when empty, so optional files stay optional
 * @return The response data, or null when the request failed
 */
export async function submitForm(form: HTMLFormElement, url: string, fileFields: string[] = []): Promise<any | null> {
    const formData = new FormData(form);

    for (const field of fileFields) {
        const file = formData.get(field);
        if (file instanceof File && file.size === 0) {
            formData.delete(field);
        }
    }

    try {
        const response = await postOnce(url, formData);
        if (!response?.ok) return null;

        response.announce();
        return response.data;
    } catch {
        // The error has already been shown to the user
        return null;
    }
}

/**
 * Shows an element only while something of the given drag type is being dragged
 * @param element The element to show, which is hidden until its "visible" class is added
 * @param mime The drag type to show it for
 */
export function showWhileDragging(element: HTMLElement, mime: string): void {
    document.addEventListener('dragstart', (event) => {
        if (event.dataTransfer?.types.includes(mime)) {
            element.classList.add('visible');
        }
    });

    document.addEventListener('dragend', () => {
        element.classList.remove('visible');
    });
}

/**
 * Makes an element accept drops of a drag type, highlighting it while something droppable hovers over it
 * @param element The element to drop on
 * @param mime The drag type it accepts
 * @param onDrop Called with the dragged data when dropped
 * @param accepts Optionally decides whether a drop is accepted at all, for example when a target is occupied
 */
export function setupDropTarget(element: HTMLElement, mime: string, onDrop: (data: string) => void, accepts: () => boolean = () => true): void {
    element.addEventListener('dragover', (event) => {
        if (!event.dataTransfer!.types.includes(mime) || !accepts()) return;

        event.preventDefault();
        event.dataTransfer!.dropEffect = 'move';
        element.classList.add('drop-hover');
    });

    element.addEventListener('dragleave', (event) => {
        if (!element.contains(event.relatedTarget as Node | null)) {
            element.classList.remove('drop-hover');
        }
    });

    element.addEventListener('drop', (event) => {
        const data = event.dataTransfer!.getData(mime);
        element.classList.remove('drop-hover');
        if (data === '' || !accepts()) return;

        event.preventDefault();
        event.stopPropagation();
        onDrop(data);
    });
}

interface ReorderableListOptions {
    /** The drag type used for the cards, so their drags can be told apart from other drags */
    mime: string;
    /** Gets the saved order of the cards, to skip saving when nothing changed */
    getSavedOrder: () => string[];
    /** Saves a new order of the cards */
    save: (order: string[]) => Promise<void>;
    /** Restores the saved order after a cancelled drag */
    restore: () => void;
}

/**
 * Makes the cards of a list reorderable by dragging. Each card needs a data-uuid and draggable="true", and must be
 * registered through the returned function every time it is rendered
 * @param list The element directly containing the cards
 * @param options How to save and restore the order
 * @return A function that registers a card
 */
export function createReorderableList(list: HTMLElement, options: ReorderableListOptions): (card: HTMLElement) => void {
    let dragged: HTMLElement | null = null;

    list.addEventListener('dragover', (event) => {
        if (dragged === null || !event.dataTransfer!.types.includes(options.mime)) return;
        event.preventDefault();

        let target = event.target as HTMLElement | null;
        while (target !== null && target.parentElement !== list) {
            target = target.parentElement;
        }
        if (target === null || target === dragged) return;

        // The dragged card takes over the slot of the hovered one, shifting it and those in between along. As the
        // dragged card ends up under the pointer afterwards, this doesn't flip back and forth on the next dragover
        const siblings = Array.from(list.children);
        const movingForward = siblings.indexOf(dragged) < siblings.indexOf(target);
        list.insertBefore(dragged, movingForward ? target.nextSibling : target);
    });

    list.addEventListener('drop', async (event) => {
        if (dragged === null) return;
        event.preventDefault();

        dragged.classList.remove('dragging');
        dragged = null;

        const order = Array.from(list.children).map((card) => (card as HTMLElement).dataset.uuid!);
        if (order.join() !== options.getSavedOrder().join()) {
            await options.save(order);
        }
    });

    return (card: HTMLElement) => {
        // Drags of elements inside the card bubble up here too, so only handle drags of the card itself
        card.addEventListener('dragstart', (event) => {
            if (event.target !== card) return;

            dragged = card;
            event.dataTransfer!.setData(options.mime, card.dataset.uuid!);
            event.dataTransfer!.effectAllowed = 'move';
            card.classList.add('dragging');
        });

        card.addEventListener('dragend', (event) => {
            if (event.target !== card) return;

            card.classList.remove('dragging');
            if (dragged !== null) {
                // The drag was cancelled, so restore the saved order
                dragged = null;
                options.restore();
            }
        });
    };
}
