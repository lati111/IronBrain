import {DataCardlist} from "../../components/datalists/DataCardlist";
import {postData, getData} from "../../main";
import {openModal, init as initModals, closeModal} from "../../components/modal";
import {showEl, hideEl, initFilters} from "./utils";

const activeFilters: Record<string, string> = { variant: 'all', ownership: 'all', itemType: 'all' };
let basePerPage = 1;

class CategoryCardlist extends DataCardlist {
    public override generateDataUrl(baseUrl: string = this.url): URL {
        const url = super.generateDataUrl(baseUrl);
        if (activeFilters.variant   !== 'all') url.searchParams.set('variant',   activeFilters.variant);
        if (activeFilters.ownership !== 'all') url.searchParams.set('ownership', activeFilters.ownership);
        return url;
    }

    public setSearch(term: string): void {
        this.searchterm = term;
    }

    public async reload(): Promise<void> {
        await this.load(true, false);
    }
}

const CATEGORIES = ['warframe', 'primary', 'secondary', 'melee', 'companion', 'companion_weapon', 'archgun', 'archmelee'] as const;
const cardlists: CategoryCardlist[] = [];
let currentCard: HTMLElement | null = null;

function calculatePerPage(): number {
    const availableWidth = document.documentElement.clientWidth;
    const cardWidth = 8.5 * 16;  // 136px — matches w-[8.5rem]
    const gap = 1.5 * 16;        // 24px — matches gap-6
    return Math.max(1, Math.floor((availableWidth + gap) / (cardWidth + gap)));
}

function setPerPage(category: string, perPage: number): void {
    const selector = document.getElementById(`${category}-cardlist-pagination-perpage-selector`) as HTMLSelectElement | null;
    if (!selector) return;
    if (!selector.querySelector(`option[value="${perPage}"]`)) {
        const option = document.createElement('option');
        option.value = String(perPage);
        option.textContent = String(perPage);
        selector.appendChild(option);
    }
    selector.value = String(perPage);
    selector.dispatchEvent(new Event('change'));
}

async function init(): Promise<void> {
    basePerPage = calculatePerPage();

    for (const category of CATEGORIES) {
        setPerPage(category, basePerPage);
        cardlists.push(new CategoryCardlist(`${category}-cardlist`));
    }

    await Promise.all(cardlists.map(list => list.init()));

    initModals();
    initFilters(activeFilters, applyFilters);

    const searchInput  = document.getElementById('armory-searchbar')             as HTMLInputElement | null;
    const searchButton = document.getElementById('armory-search-confirm-button') as HTMLButtonElement | null;

    searchButton?.addEventListener('click', () => searchAll(searchInput?.value ?? ''));
    searchInput?.addEventListener('keypress', (e: KeyboardEvent) => {
        if (e.key === 'Enter') searchAll(searchInput!.value);
    });
}

function applyFilters(): void {
    const { itemType } = activeFilters;
    const filteredPerPage = basePerPage * 2;

    for (const cat of CATEGORIES) {
        const section = document.getElementById(`section-${cat}`);
        const content = document.getElementById(`${cat}-cardlist-content`);
        if (!section) continue;

        if (itemType === 'all') {
            section.classList.remove('hidden');
            setPerPage(cat, basePerPage);
            content?.classList.add('flex-nowrap', 'overflow-x-auto');
            content?.classList.remove('flex-wrap', 'justify-center');
        } else if (cat === itemType) {
            section.classList.remove('hidden');
            setPerPage(cat, filteredPerPage);
            content?.classList.remove('flex-nowrap', 'overflow-x-auto');
            content?.classList.add('flex-wrap', 'justify-center');
        } else {
            section.classList.add('hidden');
        }
    }
}

function searchAll(term: string): void {
    for (const list of cardlists) {
        list.setSearch(term);
        list.reload();
    }
}

// ─── Typed helpers ────────────────────────────────────────────────────────────

function getInput(id: string): HTMLInputElement {
    return document.getElementById(id) as HTMLInputElement;
}

function getSelect(id: string): HTMLSelectElement {
    return document.getElementById(id) as HTMLSelectElement;
}

function getChecked(id: string): boolean {
    return (document.getElementById(id) as HTMLInputElement).checked;
}

// ─── Card quick-add (inline button) ──────────────────────────────────────────

async function addItem(card: HTMLElement): Promise<void> {
    const itemId   = (card.querySelector('input[name="item_id"]')   as HTMLInputElement).value;
    const itemType = (card.querySelector('input[name="item_type"]') as HTMLInputElement).value;

    const formData = new FormData();
    formData.append('id',   itemId);
    formData.append('type', itemType);

    const response = await postData('/api/arsenal/armory/add', formData);
    if (!response) return;

    response.announce();

    if (response.ok || response.code === 208) {
        storeUuid(card, response.data as string);
        markCardOwned(card);
        await openItemModal(card);
    }
}

// ─── Modal open ───────────────────────────────────────────────────────────────

async function openItemModal(card: HTMLElement): Promise<void> {
    currentCard = card;

    const itemId   = (card.querySelector('input[name="item_id"]')   as HTMLInputElement).value;
    const itemType = (card.querySelector('input[name="item_type"]') as HTMLInputElement).value;
    const userUuid = (card.querySelector('input[name="user_uuid"]') as HTMLInputElement).value;

    const icon = card.querySelector('img') as HTMLImageElement | null;
    (document.getElementById('modal-item-icon') as HTMLImageElement).src = icon?.src ?? '';
    (document.getElementById('modal-item-icon') as HTMLImageElement).alt = icon?.alt ?? '';
    (document.getElementById('modal-item-name') as HTMLElement).textContent =
        (card.querySelector('[data-name="name"]') as HTMLElement | null)?.textContent ?? '';

    getInput('modal-item-id').value   = itemId;
    getInput('modal-item-type').value = itemType;
    getInput('modal-user-uuid').value = userUuid;

    if (!userUuid || userUuid === 'null') {
        showEl('modal-unowned-content');
        hideEl('modal-form-content');
        hideEl('modal-save-btn');
        hideEl('modal-remove-btn');
        hideEl('modal-duplicate-btn');
        showEl('modal-add-btn');
    } else {
        const formData = new FormData();
        formData.append('uuid', userUuid);
        formData.append('type', itemType);

        const response = await getData('/api/arsenal/armory/item', formData);
        if (!response?.ok) return;

        populateForm(response.data, itemType);

        hideEl('modal-unowned-content');
        showEl('modal-form-content');
        showEl('modal-save-btn');
        showEl('modal-remove-btn');
        showEl('modal-duplicate-btn');
        hideEl('modal-add-btn');

        const nameInput = getInput('modal-name');
        const headerEl  = document.getElementById('modal-item-name') as HTMLElement;
        nameInput.oninput = () => {
            headerEl.textContent = nameInput.value.trim() || getInput('modal-base-name').value;
        };
    }

    openModal('armory-item-modal');
}

function populateForm(data: any, type: string): void {
    getInput('modal-base-name').value = data.base_name ?? '';
    getInput('modal-name').value      = data.name ?? '';
    getInput('modal-forma').value     = data.forma ?? 0;
    getInput('modal-shards').value    = String(data.shards ?? 0);
    getInput('modal-potato').checked    = !!data.potato;
    getInput('modal-built').checked     = !!data.built;
    getInput('modal-exilus').checked    = !!data.exilus;
    getInput('modal-fashioned').checked = !!data.fashioned;
    getInput('modal-riven').checked     = !!data.riven;
    getSelect('modal-school').value   = data.school ?? '';

    const potatoLabel = document.getElementById('modal-potato-label');
    if (potatoLabel) potatoLabel.textContent = type === 'weapon' ? 'Orokin Catalyst' : 'Orokin Reactor';

    setRowVisible('modal-name-row',        true);
    setRowVisible('modal-exilus-row',      type === 'warframe' || type === 'weapon');
    setRowVisible('modal-fashioned-row',   type === 'warframe' || type === 'companion');
    setRowVisible('modal-riven-row',       type === 'weapon');
    setRowVisible('modal-school-row',      type === 'warframe');
    setRowVisible('modal-shards-row',      type === 'warframe');
}

// ─── Modal actions ────────────────────────────────────────────────────────────

async function addItemFromModal(): Promise<void> {
    const formData = new FormData();
    formData.append('id',   getInput('modal-item-id').value);
    formData.append('type', getInput('modal-item-type').value);

    const response = await postData('/api/arsenal/armory/add', formData);
    if (!response) return;

    response.announce();

    if (response.ok || response.code === 208) {
        if (currentCard) {
            storeUuid(currentCard, response.data as string);
            markCardOwned(currentCard);
            await openItemModal(currentCard);
        }
    }
}

async function saveItem(): Promise<void> {
    const formData = new FormData();
    formData.append('uuid',      getInput('modal-user-uuid').value);
    formData.append('type',      getInput('modal-item-type').value);
    formData.append('forma',     getInput('modal-forma').value);
    formData.append('shards',    getInput('modal-shards').value);
    formData.append('potato',    getChecked('modal-potato')    ? '1' : '0');
    formData.append('built',     getChecked('modal-built')     ? '1' : '0');
    formData.append('exilus',    getChecked('modal-exilus')    ? '1' : '0');
    formData.append('fashioned', getChecked('modal-fashioned') ? '1' : '0');
    formData.append('riven',     getChecked('modal-riven')     ? '1' : '0');
    formData.append('school',    getSelect('modal-school').value);
    formData.append('name',      getInput('modal-name').value);

    const response = await postData('/api/arsenal/armory/update', formData);
    if (!response) return;

    response.announce();
    if (response.ok) {
        const nameEl = currentCard?.querySelector('[data-name="name"]') as HTMLElement | null;
        if (nameEl) nameEl.textContent = getInput('modal-name').value.trim() || getInput('modal-base-name').value;

        if (currentCard) updateCardIcons(currentCard);
        closeModal('armory-item-modal');
    }
}

function updateCardCounter(card: HTMLElement, field: string): void {
    const value = parseInt(getInput(`modal-${field}`).value, 10) || 0;
    card.querySelector<HTMLElement>(`[data-show-if-true-name="has_${field}"]`)?.classList.toggle('hidden', value === 0);
    const el  = card.querySelector<HTMLElement>(`[data-name="${field}"]`);
    const inp = card.querySelector<HTMLInputElement>(`input[name="${field}"]`);
    if (el)  el.textContent = String(value);
    if (inp) inp.value = String(value);
}

function updateCardIcons(card: HTMLElement): void {
    const booleans = ['built', 'fashioned', 'exilus', 'potato', 'riven'] as const;
    for (const field of booleans) {
        const active = getChecked(`modal-${field}`);
        card.querySelector<HTMLElement>(`[data-show-if-true-name="${field}"]`)?.classList.toggle('hidden', !active);
        const input = card.querySelector<HTMLInputElement>(`input[name="${field}"]`);
        if (input) input.value = active ? '1' : '0';
    }

    updateCardCounter(card, 'forma');
    updateCardCounter(card, 'shards');

    const school    = getSelect('modal-school').value;
    const schoolImg = card.querySelector<HTMLImageElement>('[data-show-if-true-name="has_school"]');
    const schoolIn  = card.querySelector<HTMLInputElement>('input[name="school"]');
    if (schoolImg) {
        if (school) {
            schoolImg.src = `/img/modules/arsenal/icon/focus/${school.toLowerCase()}.png`;
            schoolImg.classList.remove('hidden');
        } else {
            schoolImg.classList.add('hidden');
        }
    }
    if (schoolIn) schoolIn.value = school;
}

async function removeItem(): Promise<void> {
    const formData = new FormData();
    formData.append('uuid', getInput('modal-user-uuid').value);
    formData.append('type', getInput('modal-item-type').value);

    const response = await postData('/api/arsenal/armory/remove', formData);
    if (!response) return;

    response.announce();

    if (response.ok) {
        closeModal('armory-item-modal');
        if (currentCard) markCardUnowned(currentCard);
    }
}

// ─── Duplicate ────────────────────────────────────────────────────────────────

async function duplicateItem(): Promise<void> {
    const formData = new FormData();
    formData.append('uuid', getInput('modal-user-uuid').value);
    formData.append('type', getInput('modal-item-type').value);

    const response = await postData('/api/arsenal/armory/duplicate', formData);
    if (!response) return;

    response.announce();

    if (response.ok) {
        const newUuid  = response.data as string;
        const category = getCardCategory(currentCard);
        const list     = cardlists.find(l => l.dataproviderID === `${category}-cardlist`);

        closeModal('armory-item-modal');

        if (list) {
            await list.reload();
            const content = document.getElementById(`${category}-cardlist-content`);
            if (content) {
                const cards   = Array.from(content.querySelectorAll(':scope > *')) as HTMLElement[];
                const newCard = cards.find(card => {
                    const input = card.querySelector('input[name="user_uuid"]') as HTMLInputElement | null;
                    return input?.value === newUuid;
                }) ?? null;
                if (newCard) await openItemModal(newCard);
            }
        }
    }
}

function getCardCategory(card: HTMLElement | null): string {
    if (!card) return '';
    const content = card.closest('[id$="-cardlist-content"]');
    if (!content) return '';
    return content.id.replace('-cardlist-content', '');
}

// ─── DOM helpers ──────────────────────────────────────────────────────────────

function markCardOwned(card: HTMLElement): void {
    card.querySelectorAll<HTMLElement>('[data-add-class-if-true-name="unowned"]').forEach(el => el.classList.remove('opacity-40'));
    card.querySelectorAll<HTMLElement>('[data-show-if-true-name="unowned"]').forEach(el => el.classList.add('hidden'));
    card.querySelectorAll<HTMLElement>('[data-show-if-true-name="owned"]').forEach(el => el.classList.remove('hidden'));
}

function markCardUnowned(card: HTMLElement): void {
    card.querySelectorAll<HTMLElement>('[data-add-class-if-true-name="unowned"]').forEach(el => el.classList.add('opacity-40'));
    card.querySelectorAll<HTMLElement>('[data-show-if-true-name="unowned"]').forEach(el => el.classList.remove('hidden'));
    card.querySelectorAll<HTMLElement>('[data-show-if-true-name="owned"]').forEach(el => el.classList.add('hidden'));
    storeUuid(card, '');
}

function storeUuid(card: HTMLElement, uuid: string): void {
    const input = card.querySelector('input[name="user_uuid"]') as HTMLInputElement | null;
    if (input) input.value = uuid;
}

function setRowVisible(id: string, visible: boolean): void {
    visible ? showEl(id) : hideEl(id);
}

(<any>window).init             = init;
(<any>window).addItem          = addItem;
(<any>window).openItemModal    = openItemModal;
(<any>window).addItemFromModal = addItemFromModal;
(<any>window).saveItem         = saveItem;
(<any>window).removeItem       = removeItem;
(<any>window).duplicateItem    = duplicateItem;
(<any>window).openModal        = openModal;
(<any>window).closeModal       = closeModal;
