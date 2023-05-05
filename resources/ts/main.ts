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
}

(<any>window).init = init;
