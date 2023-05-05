let stored_form = null;

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

    if (window.datatableInit) {
        datatableInit();
    }
}

async function getData(url = "", data = {}) {
    const response = await fetch(url, {
      method: "GET",
      mode: "no-cors",
      cache: "no-cache",
      credentials: "same-origin",
      headers: {
        "Content-Type": "application/json",
      },
      redirect: "follow",
      referrerPolicy: "no-referrer",
    });
    return response.json();
}

function store_form(form) {
    stored_form = form;
}

function submit_stored_form() {
    stored_form.submit();
}
