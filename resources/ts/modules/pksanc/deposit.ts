import {closeModal, init as initModals} from "../../components/modal";
import {IronbrainError} from "../../Exceptions/IronbrainError";
import {toast} from "../../main";
import {postData} from "../../ajax";
import {DataSelect} from "../../components/datalists/DataSelect";

const romhackModalId: string = 'add_romhack_modal';

async function init() {
    initModals();

    const gameSelect = new DataSelect('game-selector')
    await gameSelect.init();
}

async function saveRomhack() {
    const modal = document.querySelector('#'+romhackModalId);
    if (modal === null) {
        throw new IronbrainError(`Modal with the id #${romhackModalId} was not found.`);
    }

    const nameInput = modal.querySelector('input[name="romhack_name"]') as HTMLInputElement|null;
    if (nameInput === null) {
        throw new IronbrainError(`Input with the name "romhack_name" was not found on modal with id #${romhackModalId}.`);
    }

    if (nameInput.value === '') {
        toast('A name for this romhack is required.');
        return;
    }

    const originalGameSelector = modal.querySelector('select[name="romhack_original_game"]') as HTMLSelectElement|null;
    if (originalGameSelector === null) {
        throw new IronbrainError(`Select with the name "romhack_name" was not found on modal with id #${romhackModalId}.`);
    }

    if (originalGameSelector.value === null) {
        toast('Please select an original game.');
        return;
    }

    const formdata = new FormData();
    formdata.append('name', nameInput.value);
    formdata.append('original_game', originalGameSelector.value);

    await postData('/pksanc/romhacks/add', formdata);
    closeModal(romhackModalId);
}

(<any>window).init = init;
(<any>window).saveRomhack = saveRomhack;
