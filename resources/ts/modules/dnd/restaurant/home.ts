import {DataCardlist} from "../../../components/datalists/DataCardlist";
import {postData} from "../../../main";
import {openModal, init as initModals, closeModal} from "../../../components/modal";

let restaurantCardlist: DataCardlist;

async function init(): Promise<void> {
    restaurantCardlist = new DataCardlist('restaurant-cardlist');
    await restaurantCardlist.init();

    initModals();
}

function getNameInput(): HTMLInputElement {
    return document.getElementById('restaurant-create-name') as HTMLInputElement;
}

function openCreateRestaurantModal(): void {
    const nameInput = getNameInput();
    nameInput.value = '';

    openModal('restaurant-create-modal');
    nameInput.focus();
}

async function createRestaurant(): Promise<void> {
    const formData = new FormData();
    formData.append('name', getNameInput().value.trim());

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
