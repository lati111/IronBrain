import {getData} from "../../../main";
import {openModal, init as initModals, closeModal} from "../../../components/modal";
import {restoreSidebar, submitForm, toggleSidebar} from "./shared";
import {setFloor, state} from "./manage/floor";
import {
    cancelDelete, confirmDelete, invitePlayer, openDeleteModal, removePlayer, saveBoost, saveCustomer, saveEmployee,
    saveTable, updateBoostFields,
} from "./manage/modals";
import {createRoom, deleteRoom, openCreateRoomModal, setupRoomArea} from "./manage/rooms";
import {openCreateModal, renderSidebar, switchTab} from "./manage/sidebar";

/** The manage restaurant page: the restaurant's details, its rooms and tables, and its customers, employees, players
 * and boosts. Its parts live in the manage folder */

//| Initialisation

async function init(): Promise<void> {
    const root = document.getElementById('manage') as HTMLElement;
    state.apiUrl = root.dataset.apiUrl!;
    state.editUrl = root.dataset.editUrl!;

    initModals();
    restoreSidebar();
    setupRoomArea();

    const response = await getData(state.apiUrl);
    setFloor(response.data);
}

//| Restaurant details

async function saveRestaurant(): Promise<void> {
    const form = document.getElementById('restaurant-form') as HTMLFormElement;
    if (!form.reportValidity()) return;

    const details = await submitForm(form, state.editUrl);
    if (details === null) return;

    document.getElementById('restaurant-title-name')!.textContent = details.name;
}

(<any>window).init                = init;
(<any>window).renderSidebar       = renderSidebar;
(<any>window).toggleSidebar       = toggleSidebar;
(<any>window).switchTab           = switchTab;
(<any>window).openCreateModal     = openCreateModal;
(<any>window).saveRestaurant      = saveRestaurant;
(<any>window).openCreateRoomModal = openCreateRoomModal;
(<any>window).createRoom          = createRoom;
(<any>window).deleteRoom          = deleteRoom;
(<any>window).saveTable           = saveTable;
(<any>window).saveCustomer        = saveCustomer;
(<any>window).saveEmployee        = saveEmployee;
(<any>window).saveBoost           = saveBoost;
(<any>window).updateBoostFields   = updateBoostFields;
(<any>window).invitePlayer        = invitePlayer;
(<any>window).removePlayer        = removePlayer;
(<any>window).openDeleteModal     = openDeleteModal;
(<any>window).cancelDelete        = cancelDelete;
(<any>window).confirmDelete       = confirmDelete;
(<any>window).openModal           = openModal;
(<any>window).closeModal          = closeModal;
