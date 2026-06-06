import {DataCardlist} from "../../components/datalists/DataCardlist";
import {postData} from "../../main";
import {openModal} from "../../components/modal";

class CategoryCardlist extends DataCardlist {
    public setSearch(term: string): void {
        this.searchterm = term;
    }

    public async reload(): Promise<void> {
        await this.load(true, false);
    }
}

const CATEGORIES = ['warframe', 'primary', 'secondary', 'melee', 'companion', 'companion_weapon', 'archgun', 'archmelee'] as const;
const cardlists: CategoryCardlist[] = [];

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
}

async function init(): Promise<void> {
    const perPage = calculatePerPage();

    for (const category of CATEGORIES) {
        setPerPage(category, perPage);
        cardlists.push(new CategoryCardlist(`${category}-cardlist`));
    }

    await Promise.all(cardlists.map(list => list.init()));

    const searchInput = document.getElementById('armory-searchbar') as HTMLInputElement | null;
    const searchButton = document.getElementById('armory-search-confirm-button') as HTMLButtonElement | null;

    searchButton?.addEventListener('click', () => searchAll(searchInput?.value ?? ''));
    searchInput?.addEventListener('keypress', (e: KeyboardEvent) => {
        if (e.key === 'Enter') searchAll(searchInput.value);
    });
}

function searchAll(term: string): void {
    for (const list of cardlists) {
        list.setSearch(term);
        list.reload();
    }
}

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
        const contentDiv = card.querySelector('[data-add-class-if-true-name="unowned"]') as HTMLElement | null;
        contentDiv?.classList.remove('opacity-40');

        const addBtn = card.querySelector('[data-show-if-true-name="unowned"]') as HTMLElement | null;
        addBtn?.classList.add('hidden');

        const ownedBadge = card.querySelector('[data-show-if-true-name="owned"]') as HTMLElement | null;
        ownedBadge?.classList.remove('hidden');
    }
}

(<any>window).init = init;
(<any>window).addItem = addItem;
(<any>window).openModal = openModal;
