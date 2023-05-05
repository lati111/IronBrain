let stored_form:HTMLFormElement;

function init() {
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

    // if (window.datatableInit) {
    //     window.datatableInit();
    // }
}

function store_form(form:HTMLFormElement) {
    stored_form = form;
}

function submit_stored_form() {
    stored_form.submit();
}
window.init = init;
