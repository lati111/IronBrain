import {DataCardlist} from "../../components/datalists/DataCardlist";
import {postData, getData, FetchResponse} from "../../main";
import {openModal, init as initModals, closeModal} from "../../components/modal";

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
    initFilters();

    const searchInput = document.getElementById('armory-searchbar') as HTMLInputElement | null;
    const searchButton = document.getElementById('armory-search-confirm-button') as HTMLButtonElement | null;

    searchButton?.addEventListener('click', () => searchAll(searchInput?.value ?? ''));
    searchInput?.addEventListener('keypress', (e: KeyboardEvent) => {
        if (e.key === 'Enter') searchAll(searchInput.value);
    });
}

function initFilters(): void {
    document.querySelectorAll<HTMLButtonElement>('.armory-filter-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const group = btn.dataset.filterGroup!;
            const value = btn.dataset.filterValue!;

            document.querySelectorAll<HTMLButtonElement>(`.armory-filter-btn[data-filter-group="${group}"]`).forEach(b => {
                b.classList.remove('bg-white', 'text-red-900', 'shadow-sm');
                b.classList.add('text-gray-400');
            });
            btn.classList.remove('text-gray-400');
            btn.classList.add('bg-white', 'text-red-900', 'shadow-sm');

            activeFilters[group] = value;
            applyFilters();
        });
    });
}

function calculateFilteredPerPage(): number {
    return basePerPage * 2;
}

function applyFilters(): void {
    const { itemType } = activeFilters;
    const filteredPerPage = calculateFilteredPerPage();

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

// ─── Card quick-add (inline button) ──────────────────────────────────────────

async function addItem(card: HTMLElement): Promise<void> {
    const itemId = (card.querySelector('input[name="item_id"]') as HTMLInputElement).value;
    const itemType = (card.querySelector('input[name="item_type"]') as HTMLInputElement).value;

    const formData = new FormData();
    formData.append('id', itemId);
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
    const itemType = (card.querySelector('input[name="item_type"]')  as HTMLInputElement).value;
    const userUuid = (card.querySelector('input[name="user_uuid"]')  as HTMLInputElement).value;

    const icon = card.querySelector('img') as HTMLImageElement | null;
    (document.getElementById('modal-item-icon') as HTMLImageElement).src = icon?.src ?? '';
    (document.getElementById('modal-item-icon') as HTMLImageElement).alt = icon?.alt ?? '';
    (document.getElementById('modal-item-name') as HTMLElement).textContent =
        (card.querySelector('[data-name="name"]') as HTMLElement | null)?.textContent ?? '';

    (document.getElementById('modal-item-id')   as HTMLInputElement).value = itemId;
    (document.getElementById('modal-item-type') as HTMLInputElement).value = itemType;
    (document.getElementById('modal-user-uuid') as HTMLInputElement).value = userUuid;

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

        const nameInput = document.getElementById('modal-name') as HTMLInputElement;
        const headerEl  = document.getElementById('modal-item-name') as HTMLElement;
        nameInput.oninput = () => {
            const base = (document.getElementById('modal-base-name') as HTMLInputElement).value;
            headerEl.textContent = nameInput.value.trim() || base;
        };
    }

    openModal('armory-item-modal');
}

function populateForm(data: any, type: string): void {
    (document.getElementById('modal-base-name') as HTMLInputElement).value  = data.base_name ?? '';
    (document.getElementById('modal-name')      as HTMLInputElement).value  = data.name ?? '';
    (document.getElementById('modal-forma')     as HTMLInputElement).value  = data.forma ?? 0;
    (document.getElementById('modal-potato')   as HTMLInputElement).checked = !!data.potato;
    (document.getElementById('modal-built')    as HTMLInputElement).checked = !!data.built;
    (document.getElementById('modal-exilus')   as HTMLInputElement).checked = !!data.exilus;
    (document.getElementById('modal-fashioned')as HTMLInputElement).checked = !!data.fashioned;
    (document.getElementById('modal-riven')    as HTMLInputElement).checked = !!data.riven;
    (document.getElementById('modal-school')   as HTMLSelectElement).value  = data.school ?? '';

    const potatoLabel = document.getElementById('modal-potato-label');
    if (potatoLabel) potatoLabel.textContent = type === 'weapon' ? 'Orokin Catalyst' : 'Orokin Reactor';

    setRowVisible('modal-name-row',     true);
    setRowVisible('modal-exilus-row',   type === 'warframe' || type === 'weapon');
    setRowVisible('modal-fashioned-row',type === 'warframe' || type === 'companion');
    setRowVisible('modal-riven-row',    type === 'weapon');
    setRowVisible('modal-school-row',   type === 'warframe');
}

// ─── Modal actions ────────────────────────────────────────────────────────────

async function addItemFromModal(): Promise<void> {
    const itemId   = (document.getElementById('modal-item-id')   as HTMLInputElement).value;
    const itemType = (document.getElementById('modal-item-type') as HTMLInputElement).value;

    const formData = new FormData();
    formData.append('id', itemId);
    formData.append('type', itemType);

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
    formData.append('uuid',           (document.getElementById('modal-user-uuid')      as HTMLInputElement).value);
    formData.append('type',           (document.getElementById('modal-item-type')      as HTMLInputElement).value);
    formData.append('forma',          (document.getElementById('modal-forma')          as HTMLInputElement).value);
    formData.append('potato',         (document.getElementById('modal-potato')         as HTMLInputElement).checked ? '1' : '0');
    formData.append('built',          (document.getElementById('modal-built')          as HTMLInputElement).checked ? '1' : '0');
    formData.append('exilus',         (document.getElementById('modal-exilus')         as HTMLInputElement).checked ? '1' : '0');
    formData.append('fashioned',      (document.getElementById('modal-fashioned')      as HTMLInputElement).checked ? '1' : '0');
    formData.append('riven',          (document.getElementById('modal-riven')          as HTMLInputElement).checked ? '1' : '0');
    formData.append('school',         (document.getElementById('modal-school')         as HTMLSelectElement).value);
    formData.append('name',           (document.getElementById('modal-name')           as HTMLInputElement).value);

    const response = await postData('/api/arsenal/armory/update', formData);
    if (!response) return;

    response.announce();
    if (response.ok) {
        const customName = (document.getElementById('modal-name')      as HTMLInputElement).value.trim();
        const baseName   = (document.getElementById('modal-base-name') as HTMLInputElement).value;
        const displayName = customName || baseName;

        const nameEl = currentCard?.querySelector('[data-name="name"]') as HTMLElement | null;
        if (nameEl) nameEl.textContent = displayName;

        closeModal('armory-item-modal');
    }
}

async function removeItem(): Promise<void> {
    const formData = new FormData();
    formData.append('uuid', (document.getElementById('modal-user-uuid') as HTMLInputElement).value);
    formData.append('type', (document.getElementById('modal-item-type') as HTMLInputElement).value);

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
    const userUuid = (document.getElementById('modal-user-uuid') as HTMLInputElement).value;
    const itemType = (document.getElementById('modal-item-type') as HTMLInputElement).value;

    const formData = new FormData();
    formData.append('uuid', userUuid);
    formData.append('type', itemType);

    const response = await postData('/api/arsenal/armory/duplicate', formData);
    if (!response) return;

    response.announce();

    if (response.ok) {
        const newUuid = response.data as string;
        const category = getCardCategory(currentCard);
        const list = cardlists.find(l => l.dataproviderID === `${category}-cardlist`);

        closeModal('armory-item-modal');

        if (list) {
            await list.reload();
            const content = document.getElementById(`${category}-cardlist-content`);
            if (content) {
                const cards = Array.from(content.querySelectorAll(':scope > *')) as HTMLElement[];
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
    card.querySelector('[data-add-class-if-true-name="unowned"]')?.classList.remove('opacity-40');
    card.querySelector('[data-show-if-true-name="unowned"]')?.classList.add('hidden');
    card.querySelector('[data-show-if-true-name="owned"]')?.classList.remove('hidden');
}

function markCardUnowned(card: HTMLElement): void {
    card.querySelector('[data-add-class-if-true-name="unowned"]')?.classList.add('opacity-40');
    card.querySelector('[data-show-if-true-name="unowned"]')?.classList.remove('hidden');
    card.querySelector('[data-show-if-true-name="owned"]')?.classList.add('hidden');
    storeUuid(card, '');
}

function storeUuid(card: HTMLElement, uuid: string): void {
    const input = card.querySelector('input[name="user_uuid"]') as HTMLInputElement | null;
    if (input) input.value = uuid;
}

function showEl(id: string): void {
    document.getElementById(id)?.classList.remove('hidden');
}

function hideEl(id: string): void {
    document.getElementById(id)?.classList.add('hidden');
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
