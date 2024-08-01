import {postData} from "../main";

/**
 * Attempt to sign up
 */
async function attemptSignup() {
    const form = document.querySelector('#form') as HTMLFormElement;
    if (form.checkValidity() === false) {
        return;
    }

    const formdata = new FormData(form);
    const response = await postData('/api/auth/signup', formdata);
    response?.announce();
    if (response?.ok) {
        window.location = response.headers.get('Location');
    }
}

(<any>window).attemptSignup = attemptSignup;
