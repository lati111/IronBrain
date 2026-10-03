import {postData} from "../../../main";

/** Helpers shared by the restaurant management and manage restaurant screens */

export function setField(root: HTMLElement, field: string, value: string): void {
    const element = root.querySelector(`[data-field="${field}"]`);
    if (element !== null) {
        element.textContent = value;
    }
}

export function toggleSidebar(): void {
    const sidebar = document.getElementById('inventory-sidebar')!;
    sidebar.classList.toggle('collapsed');
    localStorage.setItem('dnd-restaurant-sidebar-collapsed', sidebar.classList.contains('collapsed') ? '1' : '0');
}

/** Restores the collapsed state of the sidebar from the previous visit */
export function restoreSidebar(): void {
    if (localStorage.getItem('dnd-restaurant-sidebar-collapsed') === '1') {
        document.getElementById('inventory-sidebar')!.classList.add('collapsed');
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
        const response = await postData(url, formData);
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
