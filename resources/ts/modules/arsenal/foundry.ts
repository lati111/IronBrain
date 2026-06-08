import {DataCardlist} from "../../components/datalists/DataCardlist";
import {postData} from "../../main";
import {openModal, init as initModals, closeModal} from "../../components/modal";
import {initFilters} from "./utils";

interface ComponentData {
    uuid: string;
    name: string;
    icon: string | null;
    amount: number;
    obtained: number;
}

class FoundryCardlist extends DataCardlist {
    public readonly componentCache = new Map<string, ComponentData[]>();

    public override generateDataUrl(baseUrl: string = this.url): URL {
        const url = super.generateDataUrl(baseUrl);
        if (activeFilters.variant   !== 'all') url.searchParams.set('variant',   activeFilters.variant);
        if (activeFilters.itemType  !== 'all') url.searchParams.set('item_type', activeFilters.itemType);
        if (activeFilters.ownership !== 'all') url.searchParams.set('ownership', activeFilters.ownership);
        return url;
    }

    protected override createItem(data: { [key: string]: any }): HTMLElement {
        if (Array.isArray(data.components)) {
            this.componentCache.set(String(data.blueprint_id), data.components as ComponentData[]);
        }
        return super.createItem(data);
    }

    public async reload(): Promise<void> {
        await this.load(true, false);
    }
}

let foundryCardlist: FoundryCardlist;
let pendingCraft: { card: HTMLElement; blueprintId: string; type: string } | null = null;

const activeFilters: Record<string, string> = { variant: 'all', itemType: 'all', ownership: 'all' };

async function init(): Promise<void> {
    const contentDiv = document.getElementById('foundry-cardlist-content');
    if (contentDiv) {
        const observer = new MutationObserver((mutations) => {
            for (const mutation of mutations) {
                for (const node of mutation.addedNodes) {
                    if (node instanceof HTMLElement && node.hasAttribute('data-blueprint-item')) {
                        populateCard(node);
                    }
                }
            }
        });
        observer.observe(contentDiv, {childList: true});
    }

    foundryCardlist = new FoundryCardlist('foundry-cardlist');
    await foundryCardlist.init();

    initModals();
    initFilters(activeFilters, applyFilters);

    (<any>window).confirmCraft = confirmCraft;
}

function applyFilters(): void {
    foundryCardlist.reload();
}

function showCraftModal(card: HTMLElement, blueprintId: string, type: string): void {
    const name = card.querySelector<HTMLElement>('span[data-name="name"]')?.textContent?.trim() ?? '';
    const nameEl = document.getElementById('craft-modal-name');
    if (nameEl) nameEl.textContent = name;
    pendingCraft = { card, blueprintId, type };
    openModal('craft-modal');
}

async function confirmCraft(): Promise<void> {
    if (!pendingCraft) return;
    const { card, blueprintId, type } = pendingCraft;
    closeModal('craft-modal');
    pendingCraft = null;

    const formData = new FormData();
    formData.append('blueprint_id', blueprintId);
    formData.append('type', type);

    const response = await postData('/api/arsenal/foundry/blueprint/craft', formData);
    if (!response) return;

    if (response.ok) {
        const components = foundryCardlist.componentCache.get(blueprintId);
        if (components) {
            for (const comp of components) comp.obtained = 0;

            card.querySelectorAll<HTMLElement>('[data-component-uuid]').forEach(groupEl => {
                const comp = components.find(c => c.uuid === groupEl.dataset.componentUuid);
                if (!comp) return;
                const stackEl = groupEl.querySelector<HTMLElement>('[data-icon-stack]');
                if (stackEl) renderIconStack(comp, stackEl);
            });

            updateCompletionBar(card, components);
        }

        const pctInput = card.querySelector<HTMLInputElement>('input[name="completion_pct"]');
        if (pctInput) pctInput.value = '0';

        setCraftable(card, false);
    }

    response.announce();
}

function setCraftable(card: HTMLElement, craftable: boolean): void {
    const iconWrapper = card.querySelector<HTMLElement>('[data-blueprint-icon-wrapper]');
    if (!iconWrapper) return;

    if (craftable) {
        iconWrapper.classList.add('cursor-pointer', 'ring-2', 'ring-offset-1', 'ring-green-400', 'rounded');
        iconWrapper.title = 'Click to craft';
    } else {
        iconWrapper.classList.remove('cursor-pointer', 'ring-2', 'ring-offset-1', 'ring-green-400', 'rounded');
        iconWrapper.title = '';
        iconWrapper.onclick = null;
    }
}

function populateCard(card: HTMLElement): void {
    const pct         = parseInt((card.querySelector('input[name="completion_pct"]') as HTMLInputElement)?.value ?? '0') || 0;
    const blueprintId = (card.querySelector('input[name="blueprint_id"]') as HTMLInputElement)?.value ?? '';
    const type        = (card.querySelector('input[name="blueprint_type"]') as HTMLInputElement)?.value ?? '';
    const components  = foundryCardlist.componentCache.get(blueprintId) ?? [];

    const bar   = card.querySelector('[data-completion-bar]')   as HTMLElement | null;
    const label = card.querySelector('[data-completion-label]') as HTMLElement | null;
    if (bar)   bar.style.width = pct + '%';
    if (label) label.textContent = pct + '%';

    if (pct >= 100) {
        setCraftable(card, true);
        card.querySelector<HTMLElement>('[data-blueprint-icon-wrapper]')!
            .onclick = () => showCraftModal(card, blueprintId, type);
    }

    const slotsContainer = card.querySelector('[data-component-slots]') as HTMLElement | null;
    if (!slotsContainer) return;

    const primaryIconSrc = (card.querySelector('img[data-name="icon"]') as HTMLImageElement | null)?.src ?? null;

    slotsContainer.innerHTML = '';
    for (const comp of components) {
        if (comp.name.toLowerCase().includes('blueprint')) {
            slotsContainer.appendChild(buildBlueprintComponentGroup(comp, primaryIconSrc));
        } else {
            slotsContainer.appendChild(buildComponentGroup(comp));
        }
    }
}

function buildBlueprintComponentGroup(comp: ComponentData, primaryIconSrc: string | null): HTMLElement {
    const group   = buildComponentGroup(comp);
    const stackEl = group.querySelector<HTMLElement>('[data-icon-stack]');

    if (stackEl && primaryIconSrc) {
        const underlay = document.createElement('img');
        underlay.src              = primaryIconSrc;
        underlay.dataset.underlay = '';
        underlay.className        = 'absolute inset-0 w-full h-full object-contain pointer-events-none';
        underlay.style.zIndex     = '0';
        stackEl.append(underlay);
        // Re-render now that the underlay is present so the icon is sized correctly
        renderIconStack(comp, stackEl);
    }

    return group;
}

function buildComponentGroup(comp: ComponentData): HTMLElement {
    const group = document.createElement('div');
    group.className = 'flex flex-col items-center gap-0.5 cursor-pointer p-1 rounded hover:bg-gray-50 w-12';
    group.dataset.componentUuid = comp.uuid;

    const stackEl = document.createElement('div');
    stackEl.className = 'relative w-8 h-8 flex-shrink-0';
    stackEl.dataset.iconStack = '';
    renderIconStack(comp, stackEl);
    group.appendChild(stackEl);

    const label = document.createElement('span');
    label.className   = 'text-xs text-center leading-none break-words w-full';
    label.textContent = comp.name;
    group.appendChild(label);

    group.addEventListener('click', () => handleGroupClick(group));
    return group;
}

function renderIconStack(comp: ComponentData, container: HTMLElement): void {
    // Remove all children except the underlay so it survives click re-renders
    Array.from(container.children).forEach(child => {
        if (!child.hasAttribute('data-underlay')) child.remove();
    });

    if (comp.amount === 1) {
        const el = makeIconEl(comp, true);
        el.style.left   = '0';
        el.style.top    = '0';
        el.style.width  = '32px';
        el.style.height = '32px';
        el.style.zIndex = '1';

        const underlay = container.querySelector<HTMLElement>('[data-underlay]');
        if (underlay) {
            // Blueprint slot: overlay stays full opacity; underlay reflects obtained state
            underlay.style.opacity = comp.obtained > 0 ? '0.8' : '0.2';
            underlay.style.filter  = 'brightness(0.75)';
        } else {
            if (comp.obtained === 0) el.classList.add('opacity-40');
        }

        container.appendChild(el);
        return;
    }

    const iconPx    = 18;
    const maxShift  = Math.floor((32 - iconPx) / (comp.amount - 1));
    const shiftPx   = Math.min(maxShift, 6);
    const totalSpan = iconPx + (comp.amount - 1) * shiftPx;
    const origin    = Math.max(0, Math.floor((32 - totalSpan) / 2));

    for (let i = 0; i < comp.amount; i++) {
        const el = makeIconEl(comp, true);
        el.style.left   = (origin + i * shiftPx) + 'px';
        el.style.top    = (origin + i * shiftPx) + 'px';
        el.style.zIndex = String(i + 1); // +1 so all icons sit above underlay at z-index 0
        if (i >= comp.obtained) el.classList.add('opacity-40');
        container.appendChild(el);
    }
}

function makeIconEl(comp: ComponentData, absolute: boolean): HTMLElement {
    if (comp.icon) {
        const img = document.createElement('img');
        img.src       = comp.icon;
        img.alt       = comp.name;
        img.className = absolute ? 'object-contain ring-1 ring-white rounded-sm' : 'object-contain';
        if (absolute) {
            img.style.position = 'absolute';
            img.style.width    = '18px';
            img.style.height   = '18px';
        }
        return img;
    }

    const div = document.createElement('div');
    div.className = absolute
        ? 'bg-gray-100 rounded flex items-center justify-center ring-1 ring-white'
        : 'bg-gray-100 rounded flex items-center justify-center';
    if (absolute) {
        div.style.position = 'absolute';
        div.style.width    = '18px';
        div.style.height   = '18px';
    }
    const dash = document.createElement('span');
    dash.className   = 'text-gray-300 text-xs';
    dash.textContent = '—';
    div.appendChild(dash);
    return div;
}

async function handleGroupClick(groupEl: HTMLElement): Promise<void> {
    const card        = groupEl.closest('[data-blueprint-item]') as HTMLElement;
    const blueprintId = (card.querySelector('input[name="blueprint_id"]') as HTMLInputElement)?.value ?? '';
    const uuid        = groupEl.dataset.componentUuid!;

    const components = foundryCardlist.componentCache.get(blueprintId);
    if (!components) return;

    const comp = components.find(c => c.uuid === uuid);
    if (!comp) return;

    const newAmount = comp.obtained >= comp.amount ? 0 : comp.obtained + 1;

    const formData = new FormData();
    formData.append('component_uuid', uuid);
    formData.append('amount',         String(newAmount));

    const response = await postData('/api/arsenal/foundry/component/set', formData);
    if (!response) return;

    if (response.ok) {
        comp.obtained = newAmount;
        const stackEl = groupEl.querySelector<HTMLElement>('[data-icon-stack]');
        if (stackEl) renderIconStack(comp, stackEl);
        updateCompletionBar(card, components);
    }

    response.announce();
}

function updateCompletionBar(card: HTMLElement, components: ComponentData[]): void {
    let totalRequired = 0;
    let totalObtained = 0;
    for (const comp of components) {
        totalRequired += comp.amount;
        totalObtained += Math.min(comp.obtained, comp.amount);
    }
    const pct = totalRequired > 0 ? Math.round(totalObtained / totalRequired * 100) : 0;

    const bar   = card.querySelector('[data-completion-bar]')   as HTMLElement | null;
    const label = card.querySelector('[data-completion-label]') as HTMLElement | null;
    if (bar)   bar.style.width = pct + '%';
    if (label) label.textContent = pct + '%';
}

(<any>window).init = init;
