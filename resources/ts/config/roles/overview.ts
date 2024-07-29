import {DataTable} from "../../components/datalists/DataTable";
import {openModal, init as initModals, closeModal} from "../../components/modal";
import {postData} from "../../ajax";
import {checkboxSetter} from "../../components/datalists/utils";

let overviewTable: DataTable;
let permissionTable: DataTable|null = null;
let role_id: string;

/**
 * Initializes the role config overview
 */
async function init() {
    initModals();

    overviewTable = new DataTable('overview-table')
    await overviewTable.init();

    if (document.querySelector('#permission-table') !== null) {
        permissionTable = new DataTable('permission-table')
        permissionTable.setColumnSetter('has_permission', checkboxSetter)
        await permissionTable.init();
    }
}

/**
 * Opens the modal containing the role permissions
 * @param {HTMLTableRowElement} row The row to open the modal for
 */
async function openPermissionModal(row: HTMLTableRowElement) {
    if (permissionTable === null) {
        return;
    }

    openModal('role_permission_modal')

    role_id = (row.querySelector('input[name="id"]') as HTMLInputElement).value;
    await permissionTable.modifyUrl({
        'role_id': role_id
    })
}

/**
 * Toggles a permission on the currently selected role
 * @param {HTMLTableRowElement} row The row containing the permission info
 * @param {boolean} hasPermission Whether the role should have the permission
 */
async function togglePermission(row: HTMLTableRowElement, hasPermission: boolean) {
    if (permissionTable === null) {
        return;
    }

    const permission_id = (row.querySelector('input[name="id"]') as HTMLInputElement).value;
    const url = `/api/config/role/${role_id}/permission/${permission_id}/` + (hasPermission ? 'add' : 'remove');
    const response = await postData(url);
    response?.announce();
}

(<any>window).init = init;
(<any>window).openModal = openModal;
(<any>window).openPermissionModal = openPermissionModal;
(<any>window).togglePermission = togglePermission;

