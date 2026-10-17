/** Small pieces the service page is built from, and the formatting it shows numbers in */

//| Elements

/** Copies the element of a template, which the page's cards and rows are built from */
export function cloneTemplate(id: string): HTMLElement {
    const template = document.getElementById(id) as HTMLTemplateElement;
    return template.content.firstElementChild!.cloneNode(true) as HTMLElement;
}

/** Creates an element holding nothing but a line of text */
export function createTextElement<K extends keyof HTMLElementTagNameMap>(tag: K, className: string, text: string): HTMLElementTagNameMap[K] {
    const element = document.createElement(tag);
    element.className = className;
    element.textContent = text;
    return element;
}

export function createSpan(text: string, className: string): HTMLElement {
    return createTextElement('span', className, text);
}

/** A greyed out line saying there is nothing to show, such as "No cookers yet" */
export function createNote(text: string): HTMLElement {
    return createTextElement('p', 'text-gray-400 text-sm', text);
}

/** A greyed out list row saying a list is empty, such as "No results" */
export function createEmptyRow(text: string, padding: string): HTMLElement {
    return createTextElement('li', `text-gray-400 text-sm text-center ${padding}`, text);
}

export function addAction(container: HTMLElement, label: string, action: () => void): void {
    const button = createTextElement('button', 'interactive', label);
    button.addEventListener('click', action);
    container.appendChild(button);
}

/**
 * Creates a thin bar along the bottom of its container, filled as far as something is along
 * @param fraction How far along, from 0 to 1
 */
export function createProgressBar(fraction: number): HTMLElement {
    const bar = document.createElement('div');
    bar.className = 'kitchen-progress';

    const fill = document.createElement('div');
    fill.style.width = `${Math.round(Math.min(1, Math.max(0, fraction)) * 100)}%`;
    bar.appendChild(fill);
    return bar;
}

/** Turns an interactive card into one that can only be looked at, by copying it without its listeners */
export function makeReadOnly(card: HTMLElement): HTMLElement {
    const copy = card.cloneNode(true) as HTMLElement;
    copy.draggable = false;
    copy.classList.remove('clickable');
    copy.querySelector('[data-actions]')?.replaceChildren();
    return copy;
}

//| Formatting

/** Formats minutes as for example "45m" or "1h 05m" */
export function formatMinutes(minutes: number): string {
    if (minutes < 60) return `${minutes}m`;

    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;
    return rest === 0 ? `${hours}h` : `${hours}h ${String(rest).padStart(2, '0')}m`;
}

/** Shows how long a queued job has left, or that it is queued while nobody works on it */
export function describeJobTimer(minutesLeft: number | null): string {
    return minutesLeft !== null ? `${formatMinutes(minutesLeft)} left` : 'queued';
}

/** Shows an ingredient amount with at most two decimals */
export function formatAmount(amount: number): string {
    return String(Math.round(amount * 100) / 100);
}

/** Shows a quality added with its sign, such as "+3" or "-2" */
export function formatBonus(bonus: number): string {
    return bonus > 0 ? `+${bonus}` : String(bonus);
}

/** Shows a count of something with its noun, such as "1 portion" or "3 portions" */
export function pluralize(count: number, singular: string, plural: string): string {
    return `${count} ${count === 1 ? singular : plural}`;
}
