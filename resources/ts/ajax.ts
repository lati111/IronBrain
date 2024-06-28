//| ajax requests
import {toast} from "./main";

export async function postData(url:string, data:FormData|string = new FormData(), whitelist:string[] = []) {
    url = urlFormatter(url);

    let csrf = document.querySelector('meta[name="csrf-token"]');
    if (csrf === null) {
        console.error('Csrf token not found');
        return;
    }

    const csrfToken = csrf.getAttribute('content')
    if (csrfToken === null) {
        console.error('Csrf token not found');
        return;
    }

    const headers:{[key:string]:string} = {
        'Accept': 'application/json',
        "Sec-Fetch-Site": "same-origin",
        'X-CSRF-TOKEN': csrfToken
    }

    if (typeof data === 'string') {
        headers['Content-Type'] = 'application/json';
    }

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

export async function getData(url:string, formdata =  new FormData(), whitelist:string[] = []) {
    url = urlFormatter(url);

    let first = true;
    for (const pair of formdata.entries()) {
        if (first === true) {
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

function urlFormatter(url:string): string {
    if (url.substring(0, 4) === 'http' || url.substring(0, 5) === '/api/' || url.substring(0, 4) === 'api/' ) {
        return url;
    }

    return window.location.origin + '/' + url;
}

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
