import {DataCardlist} from "../../components/datalists/DataCardlist";
import {openModal, init as initModals, closeModal} from "../../components/modal";
import { IronbrainError } from "../../Exceptions/IronbrainError";
import {freezePage, postData, unfreezePage} from "../../main";

/**
 * Initializes the pksanc box page
 */
async function init() {
    const overview = new DataCardlist('campaign-cardlist')
    await overview.init();

    initModals();
}

function openNewCampaignModal() {
    const titleInput = document.querySelector('#new-campaign-modal input[name="title"]') as HTMLInputElement|undefined;
    if (titleInput === undefined) {
        throw new IronbrainError('title input not found on new campaign modal');
    }

    titleInput.value = '';
    openModal('new-campaign-modal');
}

async function submitNewCampaignModal() {
    const form = document.querySelector('#new-campaign-form');
    const formdata = new FormData(form);

    freezePage();
    try {
        const response = await postData(`/api/compendium/campaigns/add`, formdata);
        if (response?.ok === true) {
            //todo redirect to DM screen
        } else {
            response.announce();
        }

    } finally {
        unfreezePage();
    }
}

(<any>window).init = init;
(<any>window).openModal = openModal;
(<any>window).openNewCampaignModal = openNewCampaignModal;
(<any>window).submitNewCampaignModal = submitNewCampaignModal;
