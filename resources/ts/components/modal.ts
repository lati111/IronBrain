import { Modal } from 'flowbite'
import type { ModalOptions, ModalInterface } from 'flowbite'

var stored_form:HTMLFormElement;
var stored_modal_data:{[key: string]: string};
var opened_modal:ModalInterface;


export function openModal(modalId:string) {
    if (opened_modal !== undefined) {
        closeModal();
    }

    const $modalElement: HTMLElement = document.querySelector('#'+modalId)!;

    const modalOptions: ModalOptions = {
        placement: 'center',
        backdrop: 'dynamic',
        backdropClasses: 'bg-gray-900 bg-opacity-50 dark:bg-opacity-80 fixed inset-0 z-40',
        closable: true,
    }

    opened_modal = new Modal($modalElement, modalOptions);
    opened_modal.show();
}

export function closeModal() {
    opened_modal.hide();
}

function store_form(form:HTMLFormElement) {
    stored_form = form;
}

function store_modal_data(modal:Element) {
    stored_modal_data = {};
    const elements:NodeListOf<HTMLSelectElement|HTMLTextAreaElement|HTMLInputElement> = modal.querySelectorAll('select,textarea,input')
    for (const element of elements) {
        if (element.hasAttribute('name') === true) {
            const name:string = element.name;
            stored_modal_data[name] = element.value;
        }
    }
}

function submit_stored_form(includeModalData:Boolean = false) {
    if (includeModalData === true) {
        const data_keys = Object.keys(stored_modal_data);
        for (let i = 0; i < data_keys.length; i++) {
            const name = data_keys[i];
            const value = stored_modal_data[name]

            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            stored_form.prepend(input);
        }
    }

    stored_form.submit();
}

function load_select_modal(modal:Element, value:string) {
    modal.querySelector('select')!.value = value;
}


(<any>window).store_form = store_form;
(<any>window).store_modal_data = store_modal_data;
(<any>window).submit_stored_form = submit_stored_form;
(<any>window).load_select_modal = load_select_modal;

(<any>window).openModal = openModal;
(<any>window).closeModal = closeModal;
