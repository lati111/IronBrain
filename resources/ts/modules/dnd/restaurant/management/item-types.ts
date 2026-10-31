import {openModal} from "../../../../components/modal";
import {setField} from "../shared";
import {setFormValues, setIcon, setModalMode} from "../dom";
import {openAmountModal} from "./amount-modal";
import {describe, generalStorage, getDefinition, getIcon, getIngredientCategories, itemKey, postInventory, state} from "./inventory";
import {createListRow, saveTypeForm} from "./sidebar";
import {addToGeneral} from "./storages";
import {EquipmentType, IngredientType, ItemType, TYPE_LABELS} from "./types";

/** The ingredient and equipment types, which are stored in the storage spaces */

//| Sidebar rows

/** Renders the sidebar rows for ingredients or equipment, returning how many types exist before filtering */
export function renderTypeRows(itemType: ItemType, list: HTMLElement, search: string): number {
    const types: (IngredientType | EquipmentType)[] = itemType === 'ingredient' ? state.inventory.ingredients : state.inventory.equipment;
    const matches = types.filter((type) => {
        const haystack = 'category_label' in type ? `${type.name} ${type.category_label}` : type.name;
        return haystack.toLowerCase().includes(search);
    });

    for (const type of matches) {
        const row = createListRow();
        setIcon(row, getIcon(type));
        setField(row, 'name', type.name);

        const unsaved = generalStorage.get(itemKey(itemType, type.uuid))?.amount ?? 0;
        setField(row, 'stored', unsaved > 0 ? `${type.stored} (+${unsaved})` : `${type.stored}`);
        row.title = describe(itemType, type.uuid);

        row.querySelector('[data-action="edit"]')!.addEventListener('click', () => {
            itemType === 'ingredient' ? openIngredientModal(type.uuid) : openEquipmentModal(type.uuid);
        });

        // Tools that don't require storage are owned rather than stored, so they can also be taken away here
        const removeButton = row.querySelector('[data-action="remove"]') as HTMLElement;
        removeButton.classList.toggle('hidden', !isUnstoredTool(type) || type.stored === 0);
        if (isUnstoredTool(type)) {
            removeButton.addEventListener('click', () => {
                openAmountModal(`Remove ${type.name}`, 1, type.stored, (amount) => adjustOwned(type.uuid, -amount));
            });
        }

        row.querySelector('[data-action="add"]')!.addEventListener('click', () => openAddModal(itemType, type.uuid));

        list.appendChild(row);
    }

    return types.length;
}

/** Whether a type is equipment that doesn't require storage, which is owned rather than stored */
function isUnstoredTool(type: IngredientType | EquipmentType | null): boolean {
    return type !== null && 'requires_storage' in type && type.requires_storage === false;
}

/** Asks how many of a type to add: to the owned tools when it doesn't require storage, or else to general storage */
function openAddModal(itemType: ItemType, uuid: string): void {
    const type = getDefinition(itemType, uuid);
    const name = type?.name ?? TYPE_LABELS[itemType];

    if (isUnstoredTool(type)) {
        openAmountModal(`Add ${name}`, 1, null, (amount) => adjustOwned(uuid, amount));
    } else {
        openAmountModal(`Add ${name} to general storage`, 1, null, (amount) => addToGeneral(itemType, uuid, amount), itemType === 'ingredient');
    }
}

//| Owned equipment (tools that do not require storage)

async function adjustOwned(uuid: string, amount: number): Promise<void> {
    const formData = new FormData();
    formData.append('amount', String(amount));

    await postInventory(`${state.apiUrl}/equipment/${uuid}/owned`, formData);
}

//| Create / edit modals

export function openIngredientModal(uuid: string | null): void {
    const ingredient = uuid !== null ? state.inventory.ingredients.find((candidate) => candidate.uuid === uuid) ?? null : null;
    if (uuid !== null && ingredient === null) return;

    state.editing.ingredient = uuid;
    const form = document.getElementById('ingredient-create-form') as HTMLFormElement;
    form.reset();

    // Suggests the existing categories when entering the ingredient's category
    const categories = document.getElementById('ingredient-category-options') as HTMLDataListElement;
    categories.replaceChildren(...getIngredientCategories().map((category) => new Option(category)));
    fillParentSelect('ingredient', uuid);

    if (ingredient !== null) {
        setFormValues(form, {
            name:        ingredient.name,
            category:    ingredient.category ?? '',
            expires_in:  String(ingredient.expires_in),
            size:        String(ingredient.size),
            quality:     String(ingredient.quality),
            parent_uuid: ingredient.parent_uuid ?? '',
        });
    }

    setModalMode('ingredient', TYPE_LABELS.ingredient, ingredient !== null);
    openModal('ingredient-create-modal');
}

export function openEquipmentModal(uuid: string | null): void {
    const equipment = uuid !== null ? state.inventory.equipment.find((candidate) => candidate.uuid === uuid) ?? null : null;
    if (uuid !== null && equipment === null) return;

    state.editing.equipment = uuid;
    const form = document.getElementById('equipment-create-form') as HTMLFormElement;
    form.reset();
    fillParentSelect('equipment', uuid);

    if (equipment !== null) {
        setFormValues(form, {
            name:          equipment.name,
            category:      equipment.category,
            description:   equipment.description ?? '',
            size:          String(equipment.size),
            cleaning_time: String(equipment.cleaning_time),
            multiplier:    String(equipment.multiplier),
            parent_uuid:   equipment.parent_uuid ?? '',
        });
        // The checkbox shares its name with a hidden fallback field, so namedItem would return a RadioNodeList instead
        (form.querySelector('input[name="requires_storage"][type="checkbox"]') as HTMLInputElement).checked = equipment.requires_storage;
    }

    setModalMode('equipment', TYPE_LABELS.equipment, equipment !== null);
    openModal('equipment-create-modal');
}

/** Offers the other types of the same kind as what a type can be a variant of, as nothing can be a variant of itself */
function fillParentSelect(itemType: ItemType, excludeUuid: string | null): void {
    const types: (IngredientType | EquipmentType)[] = itemType === 'ingredient' ? state.inventory.ingredients : state.inventory.equipment;
    const select = document.getElementById(`${itemType}-parent-select`) as HTMLSelectElement;
    select.replaceChildren(new Option('None', ''), ...types
        .filter((type) => type.uuid !== excludeUuid)
        .map((type) => new Option(type.name, type.uuid)));
}

export async function createIngredient(): Promise<void> {
    await saveItemType('ingredient', document.getElementById('ingredient-create-form') as HTMLFormElement, ['icon']);
}

export async function createEquipment(): Promise<void> {
    await saveItemType('equipment', document.getElementById('equipment-create-form') as HTMLFormElement);
}

async function saveItemType(itemType: ItemType, form: HTMLFormElement, fileFields: string[] = []): Promise<void> {
    const creating = state.editing[itemType] === null;

    const response = await saveTypeForm(itemType, form, fileFields);
    if (response !== null && creating) {
        // Offer to add the new type straight away, as that is almost always the next step
        openAddModal(itemType, response.uuid);
    }
}
