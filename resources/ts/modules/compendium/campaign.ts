import {DataCardlist} from "../../components/datalists/DataCardlist";
import {openModal, init as initModals, closeModal} from "../../components/modal";
import { IronbrainError } from "../../Exceptions/IronbrainError";
import {FetchResponse, freezePage, postData, unfreezePage} from "../../main";
import {ImageUploader} from "../../components/form/image_uploader";
import {GenericFormData} from "axios";
import { cardlistDescriptionSetter, initShortenedDescriptions } from "../../components/displays/shortenedDescription";
import {initEditableFields} from "../../components/displays/editableField";

let coverUploader: ImageUploader;

/**
 * Initializes the campaign management page
 */
async function init() {
    initModals();
    initShortenedDescriptions();
    initEditableFields()

    const overview = new DataCardlist('article-cardlist');
    overview.setColumnSetter('description', cardlistDescriptionSetter);
    overview.setColumnSetter('tags', tagSetter);
    await overview.init();

    coverUploader = new ImageUploader('cover-uploader');
}

/**
 * Set the tags on a card
 * @param card The card to set the tags on
 * @param tagstring The tag string
 */
function tagSetter(card: HTMLDivElement, tagstring: string) {
    if (tagstring === null) {
        return;
    }

    const container = card.querySelector('#tags')!;
    const tags = tagstring.split(',');

    for (const tag of tags) {
        const tagSpan = document.createElement('span');
        tagSpan.classList.value = 'px-2 py-0.5 border rounded border-tertiary-gray bg-body-tertiary interactive'
        tagSpan.textContent = tag;
        container.append(tagSpan);
    }
}

/**
 * Save the edits for the opened campaign
 * @param formdata The form data to save
 */
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

/**
 * Open the modal used to create a new article
 */
function openNewArticleModal() {
    const nameInput = document.querySelector('#new-article-modal input[name="name"]') as HTMLInputElement|undefined;
    if (nameInput === undefined) {
        throw new IronbrainError('name input not found on new article modal');
    }

    const typeSelector = document.querySelector('#new-article-modal select[name="type"]') as HTMLSelectElement|undefined;
    if (typeSelector === undefined) {
        throw new IronbrainError('type select not found on new article modal');
    }

    const privateCheckbox = document.querySelector('#new-article-modal input[name="private"]') as HTMLInputElement|undefined;
    if (privateCheckbox === undefined) {
        throw new IronbrainError('private checkbox not found on new article modal');
    }

    const dmAccessCheckbox = document.querySelector('#new-article-modal input[name="dm_access"]') as HTMLInputElement|undefined;
    if (dmAccessCheckbox === undefined) {
        throw new IronbrainError('dm access checkbox not found on new article modal');
    }

    nameInput.value = '';
    typeSelector.value = '';
    privateCheckbox.checked = true;
    dmAccessCheckbox.checked = true;

    openModal('new-article-modal');
}

/**
 * Submit the new article modal to create a new article
 */
async function submitNewArticleModal() {
    const campaignUuid = (document.querySelector('input[name="campaign_uuid"]') as HTMLInputElement|null)?.value;
    if (campaignUuid === undefined) {
        throw new IronbrainError('Could not retrieve campaign uuid');
    }

    const form = document.querySelector('#new-article-form') as HTMLFormElement|null;
    if (form === null) {
        throw new IronbrainError('Form not found on new article modal');
    }

    const formdata = new FormData(form);
    formdata.set('private', formdata.get('private') === 'on' ? '1' : '0');
    formdata.set('dm_access', formdata.get('dm_access') === 'on' ? '1' : '0');

    freezePage();
    try {
        const response = await postData(`/api/compendium/campaigns/${campaignUuid}/articles/add`, formdata);
        if (response?.ok === true) {
            window.location.href = `${window.location.origin}${window.location.pathname}/articles/${response.data.uuid}`;
        } else {
            response?.announce();
        }

    } finally {
        unfreezePage();
    }
}

(<any>window).init = init;
(<any>window).saveCampaignEdits = saveCampaignEdits;
(<any>window).openNewArticleModal = openNewArticleModal;
(<any>window).submitNewArticleModal = submitNewArticleModal;
