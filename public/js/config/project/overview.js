let stored_form = null;

function store_form(form) {
    stored_form = form;
}

function submit_stored_form() {
    stored_form.submit();
}
