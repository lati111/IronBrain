import {DataCardlist} from "../../components/datalists/DataCardlist";
import {closeModal, init as initModals} from "../../components/modal";
import {postData, toast} from "../../main";

/**
 * Initializes the pksanc box page
 */
async function init() {
    initModals();

    const overview = new DataCardlist('pokedex-cardlist')
    overview.filterAddedEvent = filterAddedEvent;
    await overview.init();

    (<any>window).openFilterModal = overview.openFilterModal.bind(overview);
}

function filterAddedEvent(dataprovider, filter) {
    if (filter.type === "manual") {
        return;
    }

    closeModal("pokedex-cardlist-filter-modal");
}

async function markAsRead(card: HTMLElement, addMarking: boolean = true) {
    const pokedexId = (card.querySelector('input[type="hidden"][name="pokedex_id"]') as HTMLInputElement).value;
    const formIndex = (card.querySelector('input[type="hidden"][name="form_index"]') as HTMLInputElement).value;

    const formdata = new FormData() as FormData;
    formdata.append('pokedex_id', pokedexId)
    formdata.append('form_index', formIndex)
    formdata.append('marking', 'CAUGHT')

    let response;
    if (addMarking) {
        response = await postData('/api/pksanc/pokedex/mark', formdata);
    } else {
        response = await postData('/api/pksanc/pokedex/unmark', formdata);
    }

    response?.announce();
}

async function markAsHidden(card: HTMLElement, addMarking: boolean = true) {
    const pokedexId = (card.querySelector('input[type="hidden"][name="pokedex_id"]') as HTMLInputElement).value;
    const formIndex = (card.querySelector('input[type="hidden"][name="form_index"]') as HTMLInputElement).value;
    const visibleButton = card.querySelector('#visible_button') as HTMLButtonElement
    const hiddenButton = card.querySelector('#hidden_button') as HTMLButtonElement

    const formdata = new FormData() as FormData;
    formdata.append('pokedex_id', pokedexId)
    formdata.append('form_index', formIndex)
    formdata.append('marking', 'HIDDEN')

    let response;
    if (addMarking) {
        response = await postData('/api/pksanc/pokedex/mark', formdata);
        if (response.ok) {
            visibleButton.classList.add('hidden')
            hiddenButton.classList.remove('hidden')
        }
    } else {
        response = await postData('/api/pksanc/pokedex/unmark', formdata);
        if (response.ok) {
            visibleButton.classList.remove('hidden')
            hiddenButton.classList.add('hidden')
        }
    }

    response?.announce();
}

(<any>window).init = init;
(<any>window).markAsRead = markAsRead;
(<any>window).markAsHidden = markAsHidden;
