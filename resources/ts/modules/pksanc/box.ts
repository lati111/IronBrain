import {DataCardlist} from "../../components/datalists/DataCardlist";
import {DataSelect} from "../../components/datalists/DataSelect";
import {openModal, init as initModals, closeModal} from "../../components/modal";
import {IronbrainError} from "../../Exceptions/IronbrainError";

/**
 * Initializes the pksanc box page
 */
async function init() {
    const overview = new DataCardlist('pokemon-cardlist')
    overview.filterAddedEvent = filterAddedEvent;
    await overview.init();

    initModals();

    (<any>window).openFilterModal = overview.openFilterModal.bind(overview);
}



function filterAddedEvent() {
    closeModal("pokemon-cardlist-filter-modal");
}

(<any>window).init = init;
(<any>window).openModal = openModal;

