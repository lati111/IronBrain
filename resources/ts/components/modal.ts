import { Modal } from 'flowbite'
import type { ModalOptions, ModalInterface } from 'flowbite'

var stored_form:HTMLFormElement;
var opened_modal:ModalInterface;

function openModal(modalId:string) {
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

function closeModal() {
    opened_modal.hide();
}

function store_form(form:HTMLFormElement) {
    stored_form = form;
}

function submit_stored_form() {
    stored_form.submit();
}


(<any>window).store_form = store_form;
(<any>window).submit_stored_form = submit_stored_form;

(<any>window).openModal = openModal;
(<any>window).closeModal = closeModal;
