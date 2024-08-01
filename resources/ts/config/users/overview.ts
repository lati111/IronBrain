import {DataTable} from "../../components/datalists/DataTable";
import {openModal, init as initModals, closeModal} from "../../components/modal";
import {DataSelect} from "../../components/datalists/DataSelect";
import {freezePage, toast, unfreezePage} from "../../main";
import {postData} from "../../main";

let overviewTable: DataTable;
let roleSelector: DataSelect;
let user_uuid: string;

/**
 * Initializes the user config overview
 */
async function init() {
    initModals();

    overviewTable = new DataTable('overview-table')
    await overviewTable.init();

    roleSelector = new DataSelect('role-selector')
    await roleSelector.init();
}

function openChangeRoleModal(row: HTMLTableRowElement) {
    user_uuid = (row.querySelector('input[name="uuid"]') as HTMLInputElement).value;
    openModal('role_modal')
}

async function changeRole() {
    const roleID = roleSelector.getSelectedItem();
    if (roleID === '') {
        toast('A role must be selected to continue');
        return;
    }

    freezePage();
    try {
        const response = await postData(`/api/config/user/${user_uuid}/set_role/${roleID}`)
        response?.announce();
        if (response?.ok) {
            await overviewTable.load();
            closeModal('role_modal')
        }

    } finally {
        unfreezePage();
    }
}

(<any>window).init = init;
(<any>window).openModal = openModal;
(<any>window).openChangeRoleModal = openChangeRoleModal;
(<any>window).changeRole = changeRole;

