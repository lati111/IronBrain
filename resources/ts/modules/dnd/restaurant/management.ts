import {getData} from "../../../main";
import {openModal, init as initModals, closeModal} from "../../../components/modal";
import {EXPIRED_STORAGE_KEY, restoreSidebar, setupMoneyInput, toggleSidebar} from "./shared";
import {confirmAmount} from "./management/amount-modal";
import {saveConversion} from "./management/conversions";
import {advanceDay, showExpired} from "./management/date";
import {generalStorage, setInventory, state} from "./management/inventory";
import {createEquipment, createIngredient} from "./management/item-types";
import {addRecipeComponent, changeRecipeParent, saveRecipe, switchRecipeTab} from "./management/recipes";
import {cancelDeleteType, deleteType, openCreateTypeModal, openDeleteTypeModal, renderSidebar, switchTab} from "./management/sidebar";
import {createStorage, deleteStorage, openCreateStorageModal, openDeleteEditedStorageModal, setupStorageArea} from "./management/storages";
import {ExpiredStack} from "./management/types";

/** The inventory page: the storage spaces and what is stored in them, along with the ingredients, equipment, recipes
 * and conversions of the restaurant. Its parts live in the management folder */

//| Initialisation

async function init(): Promise<void> {
    const root = document.getElementById('inventory') as HTMLElement;
    state.apiUrl = root.dataset.apiUrl!;
    state.isPlayer = root.dataset.player === '1';

    initModals();
    document.querySelectorAll<HTMLInputElement>('input[data-money]').forEach(setupMoneyInput);

    // Players neither add items, take them out, nor arrange the storage spaces
    if (!state.isPlayer) {
        setupStorageArea();
        restoreSidebar();
    }

    window.addEventListener('beforeunload', (event) => {
        if (generalStorage.size > 0) {
            event.preventDefault();
            event.returnValue = '';
        }
    });

    const response = await getData(state.apiUrl);
    setInventory(response.data);

    // Ingredients that expired when the service that just ended moved on to the next day
    const expired = sessionStorage.getItem(EXPIRED_STORAGE_KEY);
    if (expired !== null) {
        sessionStorage.removeItem(EXPIRED_STORAGE_KEY);
        showExpired(JSON.parse(expired) as ExpiredStack[]);
    }
}

(<any>window).init                   = init;
(<any>window).renderSidebar          = renderSidebar;
(<any>window).toggleSidebar          = toggleSidebar;
(<any>window).switchTab              = switchTab;
(<any>window).openCreateStorageModal = openCreateStorageModal;
(<any>window).createStorage          = createStorage;
(<any>window).deleteStorage          = deleteStorage;
(<any>window).openDeleteEditedStorageModal = openDeleteEditedStorageModal;
(<any>window).openCreateTypeModal    = openCreateTypeModal;
(<any>window).createIngredient       = createIngredient;
(<any>window).createEquipment        = createEquipment;
(<any>window).saveRecipe             = saveRecipe;
(<any>window).changeRecipeParent     = changeRecipeParent;
(<any>window).saveConversion         = saveConversion;
(<any>window).addRecipeComponent     = addRecipeComponent;
(<any>window).switchRecipeTab        = switchRecipeTab;
(<any>window).openDeleteTypeModal    = openDeleteTypeModal;
(<any>window).cancelDeleteType       = cancelDeleteType;
(<any>window).deleteType             = deleteType;
(<any>window).confirmAmount          = confirmAmount;
(<any>window).advanceDay             = advanceDay;
(<any>window).openModal              = openModal;
(<any>window).closeModal             = closeModal;
