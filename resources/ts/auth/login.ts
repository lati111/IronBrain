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
    const location = response?.ok ? response.headers.get('Location') : null;
    if (location !== null) {
        window.location.href = location;
    }
}

(<any>window).attemptLogin = attemptLogin;
