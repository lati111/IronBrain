import {DataCardlist} from "../../components/datalists/DataCardlist";
import {closeModal, init as initModals} from "../../components/modal";
import {postData} from "../../main";

/**
 * Initializes the pksanc box page
 */
async function init() {
    const overview = new DataCardlist('pokedex-cardlist')
    overview.filterAddedEvent = filterAddedEvent;
    await overview.init();

    initModals();

    (<any>window).openFilterModal = overview.openFilterModal.bind(overview);
}

function filterAddedEvent() {
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
        response = await postData('/pksanc/pokedex/mark', formdata);
    } else {
        response = await postData('/pksanc/pokedex/unmark', formdata);
    }

    response?.announce();
}

// async function markAsHidden(card: HTMLElement, addMarking: boolean = true) {
//     const pokedexId = (card.querySelector('input[type="hidden"][name="pokedex_id"]') as HTMLInputElement).value;
//     const formIndex = (card.querySelector('input[type="hidden"][name="form_index"]') as HTMLInputElement).value;
//
//     const formdata = new FormData() as FormData;
//     formdata.append('pokedex_id', pokedexId)
//     formdata.append('form_index', formIndex)
//     formdata.append('marking', 'HIDDEN')
//
//     if (addMarking) {
//         if (await postData('/pksanc/pokedex/mark', formdata) === true) {
//             toast('Pokemon marked as caught')
//         }
//     } else {
//         if (await postData('/pksanc/pokedex/unmark', formdata) === true) {
//             toast('Pokemon no longer marked as caught')
//         }
//     }
// }

(<any>window).init = init;
(<any>window).markAsRead = markAsRead;
