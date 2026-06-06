import {DataCardlist} from "../../components/datalists/DataCardlist";
import {postData} from "../../main";
import {openModal, init as initModals, closeModal} from "../../components/modal";

class LoadoutCardlist extends DataCardlist {
    public async reload(): Promise<void> {
        await this.load(true, false);
    }
}

class SlotPickerCardlist extends DataCardlist {
    public setSearch(term: string): void {
        this.searchterm = term;
    }
    public async reload(): Promise<void> {
        await this.load(true, false);
    }
}

const SLOT_LABELS: Record<string, string> = {
    warframe:         'Select Warframe',
    primary:          'Select Primary Weapon',
    secondary:        'Select Secondary Weapon',
    melee:            'Select Melee Weapon',
    companion:        'Select Companion',
    companion_weapon: 'Select Companion Weapon',
};

let loadoutCardlist: LoadoutCardlist;
let slotPickerCardlist: SlotPickerCardlist;
let currentLoadoutCard: HTMLElement | null = null;
let currentLoadoutUuid: string = '';
let currentSlot: string = '';
let isCreatingLoadout: boolean = false;

async function init(): Promise<void> {
    loadoutCardlist = new LoadoutCardlist('loadout-cardlist');
    await loadoutCardlist.init();

    slotPickerCardlist = new SlotPickerCardlist('slot-picker-cardlist');
    await slotPickerCardlist.init();

    initModals();

    const searchInput  = document.getElementById('slot-picker-searchbar')     as HTMLInputElement | null;
    const searchButton = document.getElementById('slot-picker-search-button') as HTMLButtonElement | null;

    searchButton?.addEventListener('click', () => {
        slotPickerCardlist.setSearch(searchInput?.value ?? '');
        slotPickerCardlist.reload();
    });

    searchInput?.addEventListener('keypress', (e: KeyboardEvent) => {
        if (e.key === 'Enter') {
            slotPickerCardlist.setSearch(searchInput!.value);
            slotPickerCardlist.reload();
        }
    });
}

async function createLoadout(): Promise<void> {
    isCreatingLoadout = true;
    currentLoadoutCard = null;
    currentLoadoutUuid = '';
    currentSlot = 'warframe';

    const pickerEl = document.getElementById('slot-picker-cardlist') as HTMLElement;
    const baseUrl  = pickerEl.getAttribute('data-content-url')!.split('?')[0];
    slotPickerCardlist.url = `${baseUrl}?slot=warframe`;

    const searchInput = document.getElementById('slot-picker-searchbar') as HTMLInputElement | null;
    if (searchInput) searchInput.value = '';
    slotPickerCardlist.setSearch('');

    const labelEl = document.getElementById('slot-picker-slot-label') as HTMLElement | null;
    if (labelEl) labelEl.textContent = 'Select Warframe for New Loadout';

    hideEl('slot-picker-clear-btn');

    await slotPickerCardlist.reload();
    openModal('slot-picker-modal');
}

async function openSlotModal(slotEl: HTMLElement, slot: string): Promise<void> {
    currentSlot       = slot;
    currentLoadoutCard = slotEl.closest('[data-loadout-item]') as HTMLElement | null;
    currentLoadoutUuid = (currentLoadoutCard?.querySelector('input[name="uuid"]') as HTMLInputElement | null)?.value ?? '';

    const pickerEl = document.getElementById('slot-picker-cardlist') as HTMLElement;
    const baseUrl  = pickerEl.getAttribute('data-content-url')!.split('?')[0];
    slotPickerCardlist.url = `${baseUrl}?slot=${slot}`;

    const searchInput = document.getElementById('slot-picker-searchbar') as HTMLInputElement | null;
    if (searchInput) searchInput.value = '';
    slotPickerCardlist.setSearch('');

    const labelEl = document.getElementById('slot-picker-slot-label') as HTMLElement | null;
    if (labelEl) labelEl.textContent = SLOT_LABELS[slot] ?? slot;

    const nameEl  = currentLoadoutCard?.querySelector(`[data-name="${slot}_name"]`) as HTMLElement | null;
    const hasItem = slot !== 'warframe' && !!nameEl?.textContent?.trim();
    hasItem ? showEl('slot-picker-clear-btn') : hideEl('slot-picker-clear-btn');

    await slotPickerCardlist.reload();
    openModal('slot-picker-modal');
}

async function selectSlotItem(itemCard: HTMLElement): Promise<void> {
    const itemUuid = (itemCard.querySelector('input[name="item_uuid"]') as HTMLInputElement).value;

    if (isCreatingLoadout) {
        isCreatingLoadout = false;

        const formData = new FormData();
        formData.append('warframe_uuid', itemUuid);

        const response = await postData('/api/arsenal/loadout/create', formData);
        if (!response) return;
        response.announce();

        if (response.ok) {
            closeModal('slot-picker-modal');
            await loadoutCardlist.reload();
        }
        return;
    }

    const formData = new FormData();
    formData.append('loadout_uuid', currentLoadoutUuid);
    formData.append('slot',        currentSlot);
    formData.append('item_uuid',   itemUuid);

    const response = await postData('/api/arsenal/loadout/slot/assign', formData);
    if (!response) return;
    response.announce();

    if (response.ok && currentLoadoutCard) {
        updateCardSlot(currentLoadoutCard, currentSlot, response.data as {name: string, icon: string | null});
        closeModal('slot-picker-modal');
    }
}

async function clearSlot(): Promise<void> {
    const formData = new FormData();
    formData.append('loadout_uuid', currentLoadoutUuid);
    formData.append('slot',        currentSlot);

    const response = await postData('/api/arsenal/loadout/slot/clear', formData);
    if (!response) return;
    response.announce();

    if (response.ok && currentLoadoutCard) {
        updateCardSlot(currentLoadoutCard, currentSlot, null);
        closeModal('slot-picker-modal');
    }
}

function startRenameLoadout(nameEl: HTMLElement): void {
    const card = nameEl.closest('[data-loadout-item]') as HTMLElement;
    const uuid = (card.querySelector('input[name="uuid"]') as HTMLInputElement).value;
    const currentName = nameEl.textContent?.trim() ?? '';

    const input = document.createElement('input');
    input.type = 'text';
    input.value = currentName;
    input.className = 'underlined text-center w-full text-sm font-medium';
    input.maxLength = 32;

    nameEl.replaceWith(input);
    input.focus();
    input.select();

    let committed = false;

    const commit = async () => {
        if (committed) return;
        committed = true;

        const newName = input.value.trim();
        if (!newName || newName === currentName) {
            input.replaceWith(nameEl);
            return;
        }

        const formData = new FormData();
        formData.append('loadout_uuid', uuid);
        formData.append('name', newName);

        const response = await postData('/api/arsenal/loadout/rename', formData);
        if (response?.ok) nameEl.textContent = newName;
        input.replaceWith(nameEl);
        response?.announce();
    };

    input.addEventListener('blur', commit);
    input.addEventListener('keydown', (e: KeyboardEvent) => {
        if (e.key === 'Enter') { e.preventDefault(); input.blur(); }
        if (e.key === 'Escape') { committed = true; input.replaceWith(nameEl); }
    });
}

function updateCardSlot(card: HTMLElement, slot: string, data: {name: string, icon: string | null} | null): void {
    const iconEl        = card.querySelector(`img[data-name="${slot}_icon"]`)    as HTMLImageElement | null;
    const nameEl        = card.querySelector(`[data-name="${slot}_name"]`)       as HTMLElement | null;
    const showContainers = card.querySelectorAll(`[data-show-if-true-name="has_${slot}"]`);
    const hideContainers = card.querySelectorAll(`[data-hide-if-true-name="has_${slot}"]`);

    if (data) {
        if (iconEl) { iconEl.src = data.icon ?? ''; iconEl.alt = data.name; }
        if (nameEl) nameEl.textContent = data.name;
        showContainers.forEach(el => el.classList.remove('hidden'));
        hideContainers.forEach(el => el.classList.add('hidden'));
    } else {
        if (iconEl) { iconEl.src = ''; iconEl.alt = ''; }
        if (nameEl) nameEl.textContent = '';
        showContainers.forEach(el => el.classList.add('hidden'));
        hideContainers.forEach(el => el.classList.remove('hidden'));
    }
}

function showEl(id: string): void {
    document.getElementById(id)?.classList.remove('hidden');
}

function hideEl(id: string): void {
    document.getElementById(id)?.classList.add('hidden');
}

(<any>window).init                = init;
(<any>window).createLoadout       = createLoadout;
(<any>window).openSlotModal       = openSlotModal;
(<any>window).selectSlotItem      = selectSlotItem;
(<any>window).clearSlot           = clearSlot;
(<any>window).startRenameLoadout  = startRenameLoadout;
(<any>window).openModal           = openModal;
(<any>window).closeModal          = closeModal;
