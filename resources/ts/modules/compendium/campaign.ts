import {DataCardlist} from "../../components/datalists/DataCardlist";
import {openModal, init as initModals, closeModal} from "../../components/modal";
import { IronbrainError } from "../../Exceptions/IronbrainError";
import {FetchResponse, freezePage, postData, unfreezePage} from "../../main";
import {ImageUploader} from "../../components/form/image_uploader";
import {GenericFormData} from "axios";

let coverUploader: ImageUploader;

/**
 * Initializes the campaign management page
 */
async function init() {
    initModals();

    coverUploader = new ImageUploader('cover-uploader');
}

function revealEditButton(container:HTMLElement) {
    container.querySelector('.edit-btn').classList.replace('opacity-0', 'opacity-100')
}

function hideEditButton(container:HTMLElement) {
    container.querySelector('.edit-btn').classList.replace('opacity-100', 'opacity-0')
}

/**
 * Toggle an element from display mode to edit mode, or the opposite
 * @param {HTMLElement} container The container for the display and edit modes
 * @param {boolean} editMode Whether to switch it over to edit mode, or back to display mode
 */
function toggleEditMode(container:HTMLElement, editMode:boolean) {
    const editContainer = container.querySelector('.input-format')
    const displayContainer = container.querySelector('.display-format')

    if (editMode) {
        editContainer.classList.remove('hidden');
        displayContainer.classList.add('hidden');

        const input = editContainer.querySelector('input:not([type="file"]), textarea') as HTMLInputElement|HTMLTextAreaElement|undefined ?? null;
        if (input !== null) {
            input.selectionStart = input.value.length;
            input.selectionEnd = input.value.length;
            input.focus();
        }

    } else {
        editContainer.classList.add('hidden');
        displayContainer.classList.remove('hidden');
    }
}

async function saveTextEdit(container:HTMLElement, callback:Function) {
    const editContainer = container.querySelector('.input-format');
    const displayContainer = container.querySelector('.display-format');

    const display = editContainer.querySelector('.display') as HTMLElement|undefined;
    if (display === undefined) {
        throw new IronbrainError('Could not find display element on edit toggle')
    }

    const input = editContainer.querySelector('input') as HTMLInputElement|undefined;
    if (input === undefined) {
        throw new IronbrainError('Could not find input element on edit toggle')
    }

    const formdata = new FormData();
    formdata.append(input.name, input.value);

    const response = await callback(formdata);
    response.announce();
    if (response.ok) {
        toggleEditMode(container, false);
        displayContainer.querySelector('.display')!.textContent = input.value;
    }
}

async function saveTextAreaEdit(container:HTMLElement, callback:Function) {
    const editContainer = container.querySelector('.input-format');
    const displayContainer = container.querySelector('.display-format');

    const display = editContainer.querySelector('.display') as HTMLElement|undefined;
    if (display === undefined) {
        throw new IronbrainError('Could not find display element on edit toggle')
    }

    const textarea = editContainer.querySelector('textarea') as HTMLTextAreaElement|undefined;
    if (textarea === undefined) {
        throw new IronbrainError('Could not find textarea element on edit toggle')
    }

    const formdata = new FormData();
    formdata.append(textarea.name, textarea.value);

    const response = await callback(formdata);
    response.announce();
    if (response.ok) {
        toggleEditMode(container, false);
        displayContainer.querySelector('.display')!.textContent = textarea.value;
    }
}

async function saveImgEdit(container:HTMLElement, callback:Function) {
    const editContainer = container.querySelector('.input-format');
    const displayContainer = container.querySelector('.display-format');

    const display = displayContainer.querySelector('.display') as HTMLImageElement|undefined;
    if (display === undefined) {
        throw new IronbrainError('Could not find display element on edit toggle')
    }

    const input = editContainer.querySelector('input') as HTMLInputElement|undefined;
    if (input === undefined) {
        throw new IronbrainError('Could not find input element on edit toggle')
    }

    const formdata = new FormData();
    formdata.append(input.name, input.files![0]);

    const response = await callback(formdata);
    response.announce();
    if (response.ok) {
        toggleEditMode(container, false);
        display.src = coverUploader.imageFrame.src;
    }
}

async function saveCampaignEdits(formdata: GenericFormData): Promise<FetchResponse | undefined> {
    const uuid = (document.querySelector('input[name="campaign_uuid"]') as HTMLInputElement)?.value;

    let response;
    freezePage();
    try {
        response = await postData(`/api/compendium/campaigns/${uuid}/edit`, formdata)
    } finally {
        unfreezePage();
    }

    return response;
}

(<any>window).init = init;
(<any>window).toggleEditMode = toggleEditMode;
(<any>window).revealEditButton = revealEditButton;
(<any>window).hideEditButton = hideEditButton;
(<any>window).saveTextEdit = saveTextEdit;
(<any>window).saveTextAreaEdit = saveTextAreaEdit;
(<any>window).saveImgEdit = saveImgEdit;
(<any>window).saveCampaignEdits = saveCampaignEdits;
