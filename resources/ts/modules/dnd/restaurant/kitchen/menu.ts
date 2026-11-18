/** The small menu hovering over a meal, plate, piece of equipment or table, listing what can be done with it */

/** The element the menu is open for, so clicking it again closes the menu */
let menuAnchor: HTMLElement | null = null;

/** Adds an option to the open menu, which closes the menu before performing its action */
export type AddMenuOption = (label: string, action: () => void) => void;

export function isMenuOpen(): boolean {
    return menuAnchor !== null;
}

/**
 * Opens the small menu hovering over an element, or closes it when it is already open for that element
 * @param anchor The element the menu hovers over
 * @param status A line describing the element
 * @param build Fills the menu, either through the option adder or by adding to the options container directly
 */
export function toggleMenu(anchor: HTMLElement, status: string, build: (option: AddMenuOption, options: HTMLElement) => void): void {
    if (menuAnchor === anchor) {
        closeMealMenu();
        return;
    }

    const menu = document.getElementById('meal-menu') as HTMLElement;
    document.getElementById('meal-menu-status')!.textContent = status;

    const options = document.getElementById('meal-menu-options') as HTMLElement;
    options.replaceChildren();

    build((label, action) => {
        const button = document.createElement('button');
        button.className = 'meal-menu-option';
        button.setAttribute('role', 'menuitem');
        button.textContent = label;
        button.addEventListener('click', () => {
            closeMealMenu();
            action();
        });
        options.appendChild(button);
    }, options);

    menuAnchor = anchor;
    menu.classList.remove('hidden');
    positionMealMenu(menu, anchor);
}

/** Places the menu centred just above its element, or below it when there is no room above */
function positionMealMenu(menu: HTMLElement, anchor: HTMLElement): void {
    const gap = 6;
    const margin = 8;
    const rect = anchor.getBoundingClientRect();
    const width = menu.offsetWidth;
    const height = menu.offsetHeight;

    let top = rect.top - height - gap;
    if (top < margin) {
        top = rect.bottom + gap;
    }

    const left = Math.min(Math.max(rect.left + rect.width / 2 - width / 2, margin), window.innerWidth - width - margin);
    menu.style.top = `${top}px`;
    menu.style.left = `${left}px`;
}

export function closeMealMenu(): void {
    menuAnchor = null;
    document.getElementById('meal-menu')?.classList.add('hidden');
}

/** Closes the menu when clicking anywhere else, pressing escape, or scrolling it away from its element */
export function setupMealMenuClosing(): void {
    document.addEventListener('click', (event) => {
        const target = event.target as Node;
        const menu = document.getElementById('meal-menu') as HTMLElement;
        if (menuAnchor !== null && !menu.contains(target) && !menuAnchor.contains(target)) {
            closeMealMenu();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeMealMenu();
    });

    // Captured, so scrolling inside the kitchen panel closes it as well
    document.addEventListener('scroll', closeMealMenu, true);
    window.addEventListener('resize', closeMealMenu);
}

/**
 * Calls an action when an element is right clicked, instead of the browser's own menu
 * @param element The element
 * @param action The action
 */
export function onRightClick(element: HTMLElement, action: () => void): void {
    element.addEventListener('contextmenu', (event) => {
        event.preventDefault();
        event.stopPropagation();
        action();
    });
}
