import {Modal, ModalInterface, ModalOptions} from "flowbite";
import {IronbrainError} from "../Exceptions/IronbrainError";

let openedModal:ModalInterface|null = null;
const modals:{[key:string]:ModalInterface} = {};
const modalOptions: ModalOptions = {
    placement: 'center',
    backdrop: 'dynamic',
    backdropClasses: 'bg-gray-900 bg-opacity-50 dark:bg-opacity-80 fixed inset-0 z-40',
    closable: true,
}
const forcedModalOptions: ModalOptions = {
    placement: 'center',
    backdrop: 'dynamic',
    backdropClasses: 'bg-gray-900 bg-opacity-50 dark:bg-opacity-80 fixed inset-0 z-40',
    closable: false,
}

export function init() {
    const modalElements:NodeListOf<HTMLElement> = document.querySelectorAll('.modal')
    for (const modalElement of modalElements) {
        if(modalElement.classList.contains('forced')) {
            modals[modalElement.id!] = new Modal(modalElement, forcedModalOptions);
        } else {
            modals[modalElement.id!] = new Modal(modalElement, modalOptions);
        }
    }
}

export function openModal(modalID:string) {
    if (!(modalID in modals)) {
        throw new IronbrainError('Modal with ID "'+modalID+'" does not exist')
    }

    const modal = modals[modalID]
    modal.show();

    openedModal = modal;
}

export function closeModal(modalID:string) {
    if (!(modalID in modals)) {
        throw new IronbrainError('Modal with ID "'+modalID+'" does not exist')
    }

    const modal = modals[modalID]
    modal.hide();

    if (openedModal === modal) {
        openedModal = null;
    }
}

(<any>window).initModals = init;
(<any>window).openModal = openModal;
(<any>window).closeModal = closeModal;
