import {getData} from '../ajax.js';

async function datatableInit() {
    const datatableCollection = document.querySelectorAll('table.datatable');

    datatableCollection.forEach(async (datatable) => {
        await loadDataTable(datatable)
    });
}

async function loadDataTable(datatable: Element) {
    const url =  datatable.getAttribute('data-content-url')!;
    const data = await getData(url);

    const tbody = datatable.querySelector('tbody')!;
    tbody.innerHTML = "";

    data.forEach((rowData: any[]) => {
        const row = document.createElement('tr')
        rowData.forEach(content => {
            const cell = document.createElement('td');
            cell.classList.add('text-center');

            const cell_wrapper = getCellWrapper(datatable);
            cell_wrapper.innerHTML = content;

            cell.append(cell_wrapper);
            row.append(cell);
        })
        tbody.append(row);
    });
}

function getCellWrapper(datatable: Element) {
    const cell_wrapper = document.createElement('div');
    cell_wrapper.classList.add('flex');
    cell_wrapper.classList.add('justify-center');
    cell_wrapper.classList.add('items-center');

    switch(datatable.getAttribute('data-table-size')) {
        case 'middle':
            cell_wrapper.classList.add('datatable-middle');
            break;
    }

    return cell_wrapper;
}

(<any>window).datatableInit = datatableInit;
