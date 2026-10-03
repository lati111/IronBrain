import {DataCardlist} from "../../../components/datalists/DataCardlist";
import {postData} from "../../../main";
import {openModal, init as initModals, closeModal} from "../../../components/modal";

let restaurantCardlist: DataCardlist;

async function init(): Promise<void> {
    restaurantCardlist = new DataCardlist('restaurant-cardlist');
    await restaurantCardlist.init();

    initModals();
}

function getCreateForm(): HTMLFormElement {
    return document.getElementById('restaurant-create-form') as HTMLFormElement;
}

function openCreateRestaurantModal(): void {
    getCreateForm().reset();

    openModal('restaurant-create-modal');
    (document.getElementById('restaurant-create-name') as HTMLInputElement).focus();
}

async function createRestaurant(): Promise<void> {
    const form = getCreateForm();
    if (!form.reportValidity()) return;

    const formData = new FormData(form);
    formData.set('name', String(formData.get('name')).trim());

    const response = await postData('/api/dnd/restaurant/create', formData);
    if (!response) return;
    response.announce();

    if (response.ok) {
        closeModal('restaurant-create-modal');
        await restaurantCardlist.load(true, false);
    }
}

(<any>window).init                      = init;
(<any>window).openCreateRestaurantModal = openCreateRestaurantModal;
(<any>window).createRestaurant          = createRestaurant;
(<any>window).openModal                 = openModal;
(<any>window).closeModal                = closeModal;
