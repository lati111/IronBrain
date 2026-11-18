/** DOM helpers for the templates and create / edit modals of the restaurant management and manage restaurant screens */

/** Creates a copy of the element a template holds */
export function cloneTemplate(id: string): HTMLElement {
    const template = document.getElementById(id) as HTMLTemplateElement;
    return template.content.firstElementChild!.cloneNode(true) as HTMLElement;
}

/** Shows an image in the icon field of an element, or no image when there is none */
export function setIcon(root: HTMLElement, src: string | null): void {
    const icon = root.querySelector('[data-field="icon"]') as HTMLImageElement | null;
    if (icon === null) return;

    if (src === null) {
        icon.removeAttribute('src');
    } else {
        icon.src = src;
    }
}

/** Fills in the fields of a form by their name */
export function setFormValues(form: HTMLFormElement, values: Record<string, string>): void {
    for (const [name, value] of Object.entries(values)) {
        (form.elements.namedItem(name) as HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement).value = value;
    }
}

/**
 * Titles a create / edit modal and its buttons for either creating something new or editing what exists. The modal's
 * title, submit and delete button ids start with the given prefix, such as "table-modal-title"
 * @param prefix What the ids of the modal's title and buttons start with
 * @param label What the modal is for, such as "Table"
 * @param editing Whether something existing is edited, which can also be deleted
 */
export function setModalMode(prefix: string, label: string, editing: boolean): void {
    document.getElementById(`${prefix}-modal-title`)!.textContent = `${editing ? 'Edit' : 'New'} ${label}`;
    document.getElementById(`${prefix}-modal-submit`)!.textContent = editing ? 'Save' : 'Create';
    document.getElementById(`${prefix}-modal-delete`)!.classList.toggle('hidden', !editing);
}
