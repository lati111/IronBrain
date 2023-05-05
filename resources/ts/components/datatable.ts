import {getData} from '../ajax.js';

async function datatableInit() {
    const datatableCollection = document.querySelectorAll('table.datatable');

    datatableCollection.forEach(async (datatable) => {
        await loadDataTable(datatable)
    });
}

async function loadDataTable(datatable) {
    const url =  datatable.getAttribute('data-content-url');
    const data = await getData(url);

    const tbody = datatable.querySelector('tbody');
    tbody.innerHTML = "";

    data.forEach(rowData => {
        const row = document.createElement('tr')
        rowData.forEach(content => {
            const cell = document.createElement('td');
            cell.classList.add('text-center');
            cell.innerHTML = content;
            row.append(cell)
        })
        tbody.append(row);
    });
}

(<any>window).datatableInit = datatableInit;
