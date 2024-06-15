import {DataCardlist} from "../../components/datalists/DataCardlist";
import {closeModal, init as initModals} from "../../components/modal";

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

(<any>window).init = init;
