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
 * Initializes the article management page
 */
async function init() {
    initModals();
    initShortenedDescriptions();
    initEditableFields()

    // coverUploader = new ImageUploader('cover-uploader');
}

/**
 * Save the base edits for the opened campaign
 * @param formdata The form data to save
 */
async function saveArticleInfoEdits(formdata: GenericFormData): Promise<FetchResponse | undefined> {
    const campaignUuid = (document.querySelector('input[name="campaign_uuid"]') as HTMLInputElement)?.value;
    const articleUuid = (document.querySelector('input[name="article_uuid"]') as HTMLInputElement)?.value;

    let response;
    freezePage();
    try {
        response = await postData(`/api/compendium/campaigns/${campaignUuid}/articles/${articleUuid}/edit`, formdata)
    } finally {
        unfreezePage();
    }

    return response;
}



(<any>window).init = init;
(<any>window).saveArticleInfoEdits = saveArticleInfoEdits;
