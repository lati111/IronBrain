import { getData } from "../../ajax";

async function fillSelectWithPermissions(selectId:number, selectedPermissionId:number) {
    const select = document.querySelector<HTMLSelectElement>('#'+selectId);
    if (select === null) {
        console.error('permission selector is null');
        return false;
    }

    const url = select.getAttribute('data-url');
    if (url === null) {
        console.error('data-url is null on permission selector');
        return false;
    }

    const data = await getData(url);
    for (let i = 0; i < data.length; i++) {
        const permission = data[i];

        const id = permission['id'];
        const name = permission['name'];

        const element = document.createElement('option')
        element.value = id;
        element.textContent = name;

        if (id == selectedPermissionId) {
            element.selected = true;
        }

        select.append(element);
    };
}

(<any>window).fillSelectWithPermissions = fillSelectWithPermissions;
