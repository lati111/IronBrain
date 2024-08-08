import {DataCardlist} from "../../components/datalists/DataCardlist";
import {openModal, init as initModals, closeModal} from "../../components/modal";
import {IronbrainError} from "../../Exceptions/IronbrainError";
import {postData} from "../../main";

let excludedUuids: string[] = [];
let overview: DataCardlist;

/**
 * Initializes the pksanc box page
 */
async function init() {
    overview = new DataCardlist('pokemon-cardlist')
    overview.onLoadFinishedEvent = importIconSetter;
    await overview.init();

    initModals();
}

/**
 * Confirm the deposit and attempt to save it
 * @param {string} uuid The uuid of the staging csv
 */
async function confirmDeposit(uuid: string) {
    const json = JSON.stringify({
        'excluded_uuids': excludedUuids
    });

    const response = await postData(`/api/pksanc/deposit/staging/${uuid}/confirm`, json);
    if (response?.ok) {
        window.location = response.headers.get('Location');
        return;
    }

    response?.announce();
}

/**
 * Toggle whether a pokemon should be deposited / updated or not
 * @param {Element} card The card of the pokemon to be deposited
 * @param {boolean} shouldImport Whether the pokemon should be deposited
 */
function toggleImport(card: Element, shouldImport: boolean): void {
    const uuidInput = card.querySelector('input[name="uuid"]') as HTMLInputElement|null;
    const importIcon = card.querySelector('img.import-icon') as HTMLImageElement|null;
    const noImportIcon = card.querySelector('img.no-import-icon') as HTMLImageElement|null;
    if (uuidInput === null) {
        throw new IronbrainError('Uuid input not found on card');
    }

    if (importIcon === null) {
        throw new IronbrainError('Import icon not found on card');
    }

    if (noImportIcon === null) {
        throw new IronbrainError('Do not import icon not found on card');
    }

    const uuid = uuidInput.value;

    if (shouldImport) {
        excludedUuids.splice(excludedUuids.indexOf(uuid), 1);
        importIcon.classList.remove('hidden')
        noImportIcon.classList.add('hidden')
    } else {
        importIcon.classList.add('hidden')
        noImportIcon.classList.remove('hidden')
        excludedUuids.push(uuid)
    }
}

/**
 * A setter event to be run when the cardlist loads to set the import icons to the correct value
 */
function importIconSetter() {
    const cards= overview.body.querySelectorAll('#pokemon-cardlist-template');
    for (const card of cards) {
        const uuidInput = card.querySelector('input[name="uuid"]') as HTMLInputElement|null;
        if (uuidInput === null) {
            throw new IronbrainError('Uuid input not found on card');
        }

        const importIcon = card.querySelector('img.import-icon') as HTMLImageElement|null;
        if (importIcon === null) {
            throw new IronbrainError('Import icon not found on card');
        }

        const noImportIcon = card.querySelector('img.no-import-icon') as HTMLImageElement|null;
        if (noImportIcon === null) {
            throw new IronbrainError('Do not import icon not found on card');
        }

        const uuid = uuidInput.value;
        if (excludedUuids.includes(uuid)) {
            importIcon.classList.add('hidden')
            noImportIcon.classList.remove('hidden')
        }
    }
}

(<any>window).init = init;
(<any>window).openModal = openModal;
(<any>window).confirmDeposit = confirmDeposit;
(<any>window).toggleImport = toggleImport;
