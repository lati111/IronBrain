import {DataCardlist} from "../../../components/datalists/DataCardlist";
import {postData} from "../../../main";
import {openModal, init as initModals, closeModal} from "../../../components/modal";

let timelineCardlist: DataCardlist;

/** Whether a timeline is being created, so clicking create twice doesn't create it twice */
let creating = false;

async function init(): Promise<void> {
    timelineCardlist = new DataCardlist('timeline-cardlist');
    await timelineCardlist.init();

    initModals();
}

function getCreateForm(): HTMLFormElement {
    return document.getElementById('timeline-create-form') as HTMLFormElement;
}

function openCreateTimelineModal(): void {
    getCreateForm().reset();

    openModal('timeline-create-modal');
    (document.getElementById('timeline-create-name') as HTMLInputElement).focus();
}

async function createTimeline(): Promise<void> {
    const form = getCreateForm();
    if (creating || !form.reportValidity()) return;

    const formData = new FormData(form);
    formData.set('name', String(formData.get('name')).trim());

    // An empty file input still sends an empty file, which would fail validation as an image
    const cover = formData.get('cover_image');
    if (cover instanceof File && cover.size === 0) {
        formData.delete('cover_image');
    }

    creating = true;
    try {
        const response = await postData('/api/dnd/timeline/create', formData);
        if (!response) return;
        response.announce();

        if (response.ok) {
            closeModal('timeline-create-modal');
            await timelineCardlist.load(true, false);
        }
    } finally {
        creating = false;
    }
}

(<any>window).init                    = init;
(<any>window).openCreateTimelineModal = openCreateTimelineModal;
(<any>window).createTimeline          = createTimeline;
(<any>window).openModal               = openModal;
(<any>window).closeModal              = closeModal;
