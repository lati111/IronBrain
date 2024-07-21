import {closeModal, openModal} from "./components/modal";

const toasts:Element = document.querySelector('#toasts')!;

function toastInit() {
    setTimeout(() => {
        const errorToasts = document.querySelectorAll(".error-toast");
        for (let i = 0; i < errorToasts.length; i++) {
            const toast = errorToasts[i];
            toast.remove();
        }

        const messageToasts = document.querySelectorAll(".message-toast");
        for (let i = 0; i < messageToasts.length; i++) {
            const toast = messageToasts[i];
            toast.remove();
        }
    }, 5000);
}

export function toast(message:string) {
    let id = 'toast_'+generateRandomString(16);
    while(document.querySelector('#'+id) !== null) {
       id = 'toast_'+generateRandomString(16);
    }

    const toast = document.createElement('div');
    toast.classList.value = 'flex items-center rounded-lg bg-white shadow p-3';
    toast.id = id;

    const body = document.createElement('div');
    body.classList.value = 'ml-3 text-sm';
    body.innerText = message;
    toast.append(body)

    const closeBtn = document.createElement('button');
    closeBtn.classList.value = 'inline-flex ml-auto -mx-1.5 -my-1.5 bg-white interactive rounded-lg p-1.5 h-8 w-8'
    closeBtn.setAttribute('data-dismiss-target', '#'+id);
    closeBtn.setAttribute('aria-label', 'Close')
    closeBtn.innerHTML =
        '<span class="sr-only">Close</span>'+
        '<svg aria-hidden="true" class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">' +
        '<path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>' +
        '</svg>';
    toast.append(closeBtn)

    toasts.append(toast);
    setTimeout(removeElement.bind(null, toast), 3000)
}

/**
 * Shows a popup with a spinner for loading purposes
 */
export function freezePage() {
    openModal('load-indicator');
}

/**
 * Removed the popup with a spinner for loading purposes
 */
export function unfreezePage() {
    closeModal('load-indicator');
}

function removeElement(toast:Element) {
    toast.remove();
}

function generateRandomString(length: number): string {
    let result = '';
    const characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    const charactersLength = characters.length;
    for ( let i = 0; i < length; i++ ) {
        result += characters.charAt(Math.floor(Math.random() * charactersLength));
    }
    return result;
}

(<any>window).toastInit = toastInit;
