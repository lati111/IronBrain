import {openModal} from "../../../../components/modal";
import {setField, setupDropTarget} from "../shared";
import {setFormValues, setIcon, setModalMode} from "../dom";
import {openAmountModal} from "./amount-modal";
import {describeConversion, findConversion, getDefinition, getIcon, postInventory, roundAmount, state} from "./inventory";
import {createListRow, hideRowActions, saveTypeForm} from "./sidebar";
import {addToGeneral, getDraggedItem, removeFromGeneral} from "./storages";
import {ConversionType, DraggedItem, ITEM_MIME, TYPE_LABELS} from "./types";

/** Conversions turn an ingredient into another, such as potatoes into fries */

/**
 * Renders the sidebar rows for conversions, returning how many conversions exist before filtering. A stored stack of
 * the input dropped on a conversion is converted
 */
export function renderConversionRows(list: HTMLElement, search: string): number {
    for (const conversion of state.inventory.conversions.filter((conversion) => describeConversion(conversion).toLowerCase().includes(search))) {
        const row = createListRow();
        row.classList.add('clickable');
        setIcon(row, getIcon(getDefinition('ingredient', conversion.output_uuid)));
        setField(row, 'name', describeConversion(conversion));
        setField(row, 'stored', `${conversion.minutes} min`);
        row.querySelector('[data-field="stored"]')!.setAttribute('title', 'Minutes per piece during service');
        row.title = describeConversion(conversion);

        // Conversions are never stored, so only opening them is possible
        hideRowActions(row, 'add', 'remove', 'edit');
        row.addEventListener('click', () => openConversionModal(conversion.uuid));

        setupDropTarget(row, ITEM_MIME, (data) => openConvertModal(conversion, JSON.parse(data) as DraggedItem), () => {
            const dragged = getDraggedItem();
            return dragged !== null && dragged.type === 'ingredient' && dragged.uuid === conversion.input_uuid;
        });

        list.appendChild(row);
    }

    return state.inventory.conversions.length;
}

export function openConversionModal(uuid: string | null): void {
    const conversion = uuid !== null ? findConversion(uuid) : null;
    if (uuid !== null && conversion === null) return;

    state.editing.conversion = uuid;
    const form = document.getElementById('conversion-form') as HTMLFormElement;
    form.reset();

    for (const name of ['input_uuid', 'output_uuid']) {
        const select = form.elements.namedItem(name) as HTMLSelectElement;
        select.replaceChildren(...state.inventory.ingredients.map((ingredient) => new Option(ingredient.name, ingredient.uuid)));
    }

    if (conversion !== null) {
        setFormValues(form, {
            input_uuid:    conversion.input_uuid,
            output_uuid:   conversion.output_uuid,
            output_amount: String(conversion.output_amount),
            minutes:       String(conversion.minutes),
        });
    } else if (state.inventory.ingredients.length > 1) {
        // A conversion needs two different ingredients, so the output starts on another one than the input
        (form.elements.namedItem('output_uuid') as HTMLSelectElement).selectedIndex = 1;
    }

    setModalMode('conversion', TYPE_LABELS.conversion, conversion !== null);
    openModal('conversion-modal');
}

export async function saveConversion(): Promise<void> {
    await saveTypeForm('conversion', document.getElementById('conversion-form') as HTMLFormElement);
}

//| Converting

/** Asks how much of a dragged stack to convert, at most all of it */
function openConvertModal(conversion: ConversionType, item: DraggedItem): void {
    const input = getDefinition('ingredient', conversion.input_uuid)?.name ?? 'ingredient';
    const output = getDefinition('ingredient', conversion.output_uuid)?.name ?? 'ingredient';
    openAmountModal(`Convert ${input} into ${output}`, item.amount, item.amount, (amount) => convertItem(conversion, item, amount), true);
}

/**
 * Converts part of a stack, with what it becomes going where the stack is. Stacks in the unsaved general storage are
 * converted there, while stored ones are converted by the server
 */
async function convertItem(conversion: ConversionType, item: DraggedItem, amount: number): Promise<void> {
    if (item.from === null) {
        removeFromGeneral('ingredient', item.uuid, amount, item.days_left ?? null);
        addToGeneral('ingredient', conversion.output_uuid, roundAmount(amount * conversion.output_amount));
        return;
    }

    const formData = new FormData();
    formData.append('amount', String(amount));
    formData.append('from_storage_uuid', item.from);
    if (item.stack_uuid !== undefined) formData.append('stack_uuid', item.stack_uuid);

    await postInventory(`${state.apiUrl}/conversion/${conversion.uuid}/convert`, formData);
}
