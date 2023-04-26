function init() {
    setTimeout(() => {
        const errorToasts = document.querySelectorAll(".error-toast");
        const messageToasts = document.querySelectorAll(".message-toast");

        const toasts = errorToasts.concat(messageToasts)
        for (let i = 0; i < toasts.length; i++) {
            const toast = toasts[i];
            toast.remove();
        }
    }, 5000);
}
