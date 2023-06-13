var filePreview: Element;
var fileUploader: Element;
var fileInput: HTMLInputElement;
var imageFrame: HTMLImageElement;
var disabledCheckbox: HTMLInputElement | null;

function imageUploaderInit
    (
        filePreviewId: string,
        fileUploaderId: string,
        imageFrameId: string,
        disabledCheckboxId: string | null = null
    ) {

    const preview = document.querySelector("#" + filePreviewId);
    const uploader = document.querySelector<HTMLInputElement>("#" + fileUploaderId);
    const frame = document.querySelector<HTMLImageElement>("#" + imageFrameId);

    if (preview === null) {
        console.error('Image uploader preview `' + filePreviewId + '` does not exist');
        return uploader;
    } else if (uploader === null) {
        console.error('Image uploader input `' + fileUploaderId + '` does not exist');
        return false;
    } else if (frame === null) {
        console.error('Image uploader frame `' + imageFrameId + '` does not exist');
        return false;
    }

    const input = uploader.querySelector('input');
    if (input === null) {
        console.error('Image uploader input inside of `' + filePreviewId + '` does not exist');
        return uploader;
    }

    filePreview = preview;
    fileUploader = uploader;
    fileInput = input;
    imageFrame = frame;
    disabledCheckbox = document.querySelector("#" + disabledCheckboxId);
}

function toggleThumbnailField() {
    if (disabledCheckbox === null) {
        console.error('Disabled checkbox for image uploader does not exist');
        return false;
    }

    const disabledBlurb: Element | null = filePreview.querySelector(".disabledBlurb");
    if (disabledBlurb === null) {
        console.error('Image uploader does not contain a disabled blurb');
        return false;
    }

    const removeButton: Element | null = filePreview.querySelector(".removeButton");
    if (removeButton === null) {
        console.error('Image uploader does not contain a remove button');
        return false;
    }

    if (disabledCheckbox.checked) {
        filePreview.classList.replace('flex', 'hidden');
        filePreview.classList.add('disabled');
        disabledBlurb.classList.add('hidden')
        removeButton.classList.remove('hidden')
        fileUploader.classList.remove('hidden')
    } else {
        fileUploader.classList.add('hidden')
        filePreview.classList.add('remove');
        disabledBlurb.classList.remove('hidden')
        removeButton.classList.add('hidden')
        filePreview.classList.replace('hidden', 'flex')
    }
}

function preview(e: { target: { files: (Blob | MediaSource)[]; }; }) {
    let file;
    if (e.target.files && e.target.files[0]) {
      file = e.target.files[0]
    } else {
        return false;
    }

    imageFrame.src = URL.createObjectURL(file);
    fileUploader.classList.add('hidden')
    filePreview.classList.replace('hidden', 'flex')
}

function clearImage() {
    fileInput.value = "";
    filePreview.classList.replace('flex', 'hidden');
    fileUploader.classList.remove('hidden')
    imageFrame.src = "";
}

(<any>window).imageUploaderInit = imageUploaderInit;
(<any>window).toggleThumbnailField = toggleThumbnailField;
(<any>window).preview = preview;
(<any>window).clearImage = clearImage;
