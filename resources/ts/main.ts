import {closeModal, openModal} from "./components/modal";
import {GenericFormData} from "axios";

const toasts:Element = document.querySelector('#toasts')!;

//| Toasts

/**
 * Initializes the toasts on the page
 */
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

/**
 * Shows a toast on the page
 * @param {string} message The message to display
 */
export function toast(message:string) {
    let id = 'toast_'+generateRandomString(16);
    while(document.querySelector('#'+id) !== null) {
       id = 'toast_'+generateRandomString(16);
    }

    const toast = document.createElement('div');
    toast.classList.value = 'flex items-center rounded-lg bg-white shadow p-3 z-[100]';
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
    setTimeout(function (toast: HTMLElement) {
        toast.remove();
    }.bind(null, toast), 3000)
}

//| Data operations

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

/**
 * Performs a post operation to the given url
 * @param {string} url The url to post to
 * @param {GenericFormData|string} data The parameters in either json, of formdata format
 * @param {string[]} whitelist A whitelist errors that are allowed to be shown to the user
 * @return {FetchResponse} The response object
 */
export async function postData(url: string, data: GenericFormData|string = new FormData, whitelist: string[] = []) {
    url = urlFormatter(url);

    let csrf = document.querySelector('meta[name="csrf-token"]') as Element|null;
    if (csrf === null) {
        console.error('Csrf token not found');
        return;
    }

    const csrfToken = csrf.getAttribute('content') as Element|null
    if (csrfToken === null) {
        console.error('Csrf token not found');
        return;
    }

    const headers:{ Accept: string; "X-CSRF-TOKEN": Element; "Sec-Fetch-Site": string } = {
        'Accept': 'application/json',
        "Sec-Fetch-Site": "same-origin",
        'X-CSRF-TOKEN': csrfToken
    }

    if (typeof data === 'string') {
        headers['Content-Type'] = 'application/json';
    }


    // @ts-ignore
    const response = await fetch(url, {
        method: 'post',
        body: data,
        headers: headers
    });

    const responseData = await response.json();
    const fetchResponse = new FetchResponse(response.ok, response.status, responseData);

    if (!response.ok) {
        handleFetchError(fetchResponse, whitelist)
    }

    return fetchResponse
}

/**
 * Performs a get operation to the given url
 * @param {string} url The url to post to
 * @param {formdata} formdata The parameters in a formdata format
 * @param {string[]} whitelist A whitelist errors that are allowed to be shown to the user
 * @return {FetchResponse} The response object
 */
export async function getData(url:string, formdata =  new FormData(), whitelist:string[] = []) {
    url = urlFormatter(url);

    let first = true;
    for (const pair of formdata.entries()) {
        if (first) {
            first = false;
            url += '?';
        } else {
            url += '&';
        }

        url += `${pair[0]}=${pair[1]}`
    }

    const response = await fetch(url, {
        method: 'get',
        headers: {
            'Accept': 'application/json',
            "Sec-Fetch-Site": "same-origin"
        }
    });

    const data = await response.json();
    const fetchResponse = new FetchResponse(response.ok, response.status, data);

    if (!response.ok) {
        handleFetchError(fetchResponse, whitelist)
    }

    return new FetchResponse(response.ok, response.status, data);
}

/**
 * Format a url to a valid format
 * @param {string} url The url to format
 * @return {string} The formatted url
 */
function urlFormatter(url:string): string {
    if (url.substring(0, 4) === 'http' || url.substring(0, 5) === '/api/' || url.substring(0, 4) === 'api/' ) {
        return url;
    }

    return window.location.origin + '/' + url;
}

/**
 * Attempts to handle an error for the fetch response
 * @param {FetchResponse} fetchResponse The failed format to handle
 * @param {string[]} whitelist A whitelist errors that are allowed to be shown to the user
 */
function handleFetchError(fetchResponse:FetchResponse, whitelist:string[] = []) {
    if (fetchResponse.code >= 500) {
        toast('An error occured. Please try again later.')
        throw new Error(fetchResponse.data);
    }

    if (typeof fetchResponse.data === 'string') {
        toast(fetchResponse.data);
    }

    if (typeof fetchResponse.data === 'object') {
        for (let [key, value] of Object.entries(fetchResponse.data)) {
            if (typeof value === 'object') {
                //@ts-ignore
                value = value[0]
            }

            if (typeof value !== 'string') {
                continue;
            }

            if (!whitelist.includes(key) && whitelist.length > 0) {
                console.error(value)
                continue;
            }

            toast(value)
            throw new Error(value);
        }
    }

    toast('An error occured. Please try again later.')
    throw new Error('failed fetch request');
}

//| String utils

/**
 * Format a string into proper display format
 * @param {string} string The string to format
 * @return {string} The formatted string
 */
export function toDisplayString(string:string):string {
    string = string.replace('_', ' ');
    return string;
}

/**
 * Generate a random string
 * @param {number} length The length of the string to generate
 * @return {string} The generated string
 */
function generateRandomString(length: number): string {
    let result = '';
    const characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    const charactersLength = characters.length;
    for ( let i = 0; i < length; i++ ) {
        result += characters.charAt(Math.floor(Math.random() * charactersLength));
    }
    return result;
}

//| Global function setters
(<any>window).toastInit = toastInit;

//| Data classes
export class FetchResponse {
    ok:boolean
    code:number
    message:string
    data:any

    constructor(ok:boolean, code:number, data:{'message':string, 'data':any, 'errors':any}) {
        this.ok = ok;
        this.code = code;
        this.message = data.message;
        if (code >= 200 && code < 300) {
            this.data = data.data;
        } else {
            this.data = data.errors;
        }
    }

    announce() {
        toast(this.message)
    }
}
