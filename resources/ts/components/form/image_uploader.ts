import {IronbrainError} from "../../Exceptions/IronbrainError";

export class ImageUploader {
    public id;

    /** @type {HTMLElement} The file input container */
    protected fileInputContainer: HTMLElement;

    /** @type {HTMLInputElement} The actual file input */
    protected fileInput: HTMLInputElement;

    /** @type {HTMLElement} The file preview container */
    protected filePreviewContainer: HTMLElement;

    /** @type {HTMLImageElement} The frame displaying the preview image */
    public imageFrame: HTMLImageElement;

    public constructor(id: string) {
        this.id = id;

        const element = document.querySelector('#'+this.id) as HTMLElement|undefined;
        if (element === undefined) {
            throw new IronbrainError(`An image uploader with the id '${this.id}' was not found.`)
        }

        const fileInputContainer = element.querySelector('.image-upload-container') as HTMLElement|undefined;
        if (fileInputContainer === undefined) {
            throw new IronbrainError(`The image upload container was not found in the image uploader`)
        }

        const fileInput = element.querySelector('.file-input') as HTMLInputElement|undefined;
        if (fileInput === undefined) {
            throw new IronbrainError(`The file input was not found in the image uploader`)
        }

        const filePreviewContainer = element.querySelector('.file-preview') as HTMLElement|undefined;
        if (filePreviewContainer === undefined) {
            throw new IronbrainError(`The image preview container was not found in the image uploader`)
        }

        const imageFrame = element.querySelector('.img-frame') as HTMLImageElement|undefined;
        if (imageFrame === undefined) {
            throw new IronbrainError(`The image frame was not found in the image uploader`)
        }

        const imageClearButton = element.querySelector('.image-clear-button') as HTMLButtonElement|undefined;
        if (imageClearButton === undefined) {
            throw new IronbrainError(`The image clear button was not found in the image uploader`)
        }

        this.fileInputContainer = fileInputContainer;

        this.fileInput = fileInput;
        this.fileInput.addEventListener('change', this.preview.bind(this));

        this.filePreviewContainer = filePreviewContainer;
        this.imageFrame = imageFrame;

        imageClearButton.addEventListener('click', this.clearPreview.bind(this))
    }

    /**
     * Display the uploaded image as a preview image
     * Triggered after an image is uploaded.
     * @protected
     */
    protected preview() {
        const files = this.fileInput.files;

        let file;
        if (files && files[0]) {
            file = files[0]
        } else {
            return false;
        }

        this.imageFrame.src = URL.createObjectURL(file);
        this.fileInputContainer.classList.add('hidden')
        this.filePreviewContainer.classList.replace('hidden', 'flex')
    }

    /**
     * Clears the preview image and show the uploader
     * Triggered after pressing the clear button
     * @protected
     */
    protected clearPreview() {
        this.filePreviewContainer.classList.replace('flex', 'hidden');
        this.fileInput.value = "";
        this.fileInputContainer.classList.remove('hidden')
        this.imageFrame.src = "";
    }
}
