import {postData} from "../main";

/**
 * Initializes the signup overview
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
        window.location = response.headers.get('Location')+"?message=Your account has been created.";
    }
}

(<any>window).attemptSignup = attemptSignup;
