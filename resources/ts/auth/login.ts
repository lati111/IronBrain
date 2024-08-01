import {postData} from "../main";

/**
 * Attempt to log in
 */
async function attemptLogin() {
    const form = document.querySelector('#form') as HTMLFormElement;
    if (form.checkValidity() === false) {
        return;
    }

    const formdata = new FormData(form);
    const response = await postData('/api/auth/login', formdata);
    response?.announce();
    if (response?.ok) {
        window.location = response.headers.get('Location');
    }
}

(<any>window).attemptLogin = attemptLogin;
