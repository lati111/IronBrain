import { getData } from '../../ajax.js';
import { toDisplayString, toast } from '../../utilities.js';
import { openModal, closeModal } from '../modal';

let dataproviderID:string|null;
let modal:HTMLElement|null;
let dataUrl:URL|null;
let type:string|null;

async function openFilterlist(dataProviderID:string, modalID:string, urlString:string) {
    dataproviderID = dataProviderID;
    dataUrl = new URL(urlString);
    modal = document.querySelector('#'+modalID)!;
    const filterSelector:HTMLSelectElement = modal.querySelector('select[name="filter-select"]')!;
    const elements:NodeListOf<HTMLSelectElement|HTMLInputElement> = modal.querySelectorAll('select:not(select[name="filter-select"]),input')
    for (const element of elements) {
        element.classList.add('hidden');
    }

    filterSelector.removeEventListener('change', loadSelectedFilter, true);
    filterSelector.addEventListener('change', loadSelectedFilter, true);
    openModal(modalID);

    const filters = await getData(dataUrl.toString());
    filterSelector.innerHTML = '<option class="placeholder hidden">Choose filter</option>';
    for (const filter of filters) {
        const filterOption = document.createElement('option');
        filterOption.textContent = toDisplayString(filter);
        filterOption.value = filter;
        filterSelector.append(filterOption);
    }
}

function addFilter() {
    if (modal === null || type === null) {
        return;
    }

    const filterSelector:HTMLSelectElement = modal.querySelector('select[name="filter-select"]')!;
    const filter = filterSelector.value;
    let operatorText:string = '';
    let operator:string = '';
    let value:string = '';

    switch(type) {
        case 'number':
        case 'date':
        case 'select':
            const operatorSelector:HTMLSelectElement = modal.querySelector('select[name="operator-select"]')!;
            operatorText = operatorSelector.children[operatorSelector.selectedIndex].textContent!
            operator = operatorSelector.value;
            break;
        case 'bool':
            const boolSelector:HTMLSelectElement = modal.querySelector('select[name="bool-select"]')!;
            operatorText = boolSelector.children[boolSelector.selectedIndex].textContent!
            operator = boolSelector.value;
    }

    switch(type) {
        case 'number':
            const numberInput:HTMLInputElement = modal.querySelector('input[name="number-input"]')!;
            value = numberInput.value;
            break;
        case 'date':
            const dateInput:HTMLInputElement = modal.querySelector('input[name="date-input"]')!;
            value = dateInput.value;
            break;
        case 'select':
            const valueSelector:HTMLSelectElement = modal.querySelector('select[name="value-select"]')!;
            value = valueSelector.value;
            break;
    }

    const filterlistElement:HTMLElement = document.querySelector('#'+dataproviderID+'-filterlist')!;
    const filterContainer:HTMLElement = filterlistElement.querySelector('.filter-container')!;

    if (filterContainer.querySelector(`div[data-filter="${filter}"][data-operator="${operator}"][data-value="${value}"]`) !== null) {
        toast('Filter already exists')
    }

    filterContainer.prepend(generateFilter(type, filter, operator, operatorText, value))
    closeModal();
}

function generateFilter(type:string, filter:string, operator:string, operatorText:string, value:string) {
    const filterElement = document.createElement('div');
    filterElement.classList.add('filter');
    filterElement.classList.add('px-2');
    filterElement.classList.add('py-1');
    filterElement.classList.add('red-underlined');
    filterElement.classList.add('flex');
    filterElement.setAttribute('data-filter', filter);
    filterElement.setAttribute('data-operator', operator);
    filterElement.setAttribute('data-value', value);

    switch(type) {
        case 'number':
        case 'date':
        case 'select':
            filterElement.innerHTML = `<span>${toDisplayString(filter)} ${operatorText} ${value}</span>`
            break;
        case 'bool':
            filterElement.innerHTML = `<span>${operatorText} ${toDisplayString(filter)}</span>`
    }

    filterElement.innerHTML +=
        `<button type="button" class="pl-1.5 ml-auto" onclick="this.closest('.filter').remove()">
            <img src="/img/icons/x.svg" alt="close" class="interactive w-5 h-5">
        </button>`;

    return filterElement;
}

async function loadSelectedFilter() {
    if (modal === null || dataUrl === null) {
        return;
    }

    const filterSelector:HTMLSelectElement = modal.querySelector('select[name="filter-select"]')!;
    const elements:NodeListOf<HTMLSelectElement|HTMLInputElement> = modal.querySelectorAll('select:not(select[name="filter-select"]),input')
    for (const element of elements) {
        element.classList.add('hidden');
    }

    const url = dataUrl;
    url.searchParams.append('filter', filterSelector.value);

    const response = await getData(url.toString());
    type = response['type'];
    switch(response['type']) {
        case 'select':
            fillOperatorSelect(response['operators'])
            fillValueSelect(response['options'])
            break;
        case 'number':
            fillOperatorSelect(response['operators'])
            setNumberInput(response['options'])
            break;
        case 'date':
            fillOperatorSelect(response['operators'])
            setDateInput(response['options'])
            break;
        case 'bool':
            fillBoolSelect(response['operators'])
            break;
    }


}

function fillOperatorSelect(operators:Array<{'operator':string, 'text':string}>) {
    if (modal === null) {
        return;
    }

    const operatorSelector:HTMLSelectElement = modal.querySelector('select[name="operator-select"]')!;
    operatorSelector.classList.remove('hidden');
    operatorSelector.innerHTML = '';

    for (const operator of operators) {
        const operatorOption = document.createElement('option');
        operatorOption.textContent = operator.text;
        operatorOption.value = operator.operator;
        operatorSelector.append(operatorOption);
    }
}

function fillValueSelect(values:Array<string>) {
    if (modal === null) {
        return;
    }

    const valueSelector:HTMLSelectElement = modal.querySelector('select[name="value-select"]')!;
    valueSelector.classList.remove('hidden');
    valueSelector.innerHTML = '';

    for (const value of values) {
        const valueOption = document.createElement('option');
        valueOption.textContent = toDisplayString(value);
        valueOption.value = value;
        valueSelector.append(valueOption);
    }
}

function setNumberInput(options:{'min': string, 'max': string}) {
    if (modal === null) {
        return;
    }

    const numberInput:HTMLInputElement = modal.querySelector('input[name="number-input"]')!;
    numberInput.classList.remove('hidden');
    numberInput.min = options.min
    numberInput.max = options.max
    numberInput.value = options.min;
}

function setDateInput(options:{'min': string, 'max': string}) {
    if (modal === null) {
        return;
    }

    const dateInput:HTMLInputElement = modal.querySelector('input[name="date-input"]')!;
    dateInput.classList.remove('hidden');
    dateInput.min = options.min
    dateInput.max = options.max
    dateInput.value = options.min;
}

function fillBoolSelect(operators:Array<{'operator':string, 'text':string}>) {
    if (modal === null) {
        return;
    }

    const boolSelector:HTMLSelectElement = modal.querySelector('select[name="bool-select"]')!;
    boolSelector.classList.remove('hidden');
    boolSelector.innerHTML = '';

    for (const operator of operators) {
        const operatorOption = document.createElement('option');
        operatorOption.textContent = operator.text;
        operatorOption.value = operator.operator;
        boolSelector.append(operatorOption);
    }
}

(<any>window).openFilterlist = openFilterlist;
(<any>window).addFilter = addFilter;
