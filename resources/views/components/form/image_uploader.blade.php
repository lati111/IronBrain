<div id="{{$id}}" class="flex justify-center {{$cls ?? ''}}">
    <div class="image-upload-container file-drop-area file-area dashed-border w-full @if(isset($src) === true) hidden @endif">
        <span class="choose-file-button interactive">Choose image </span>

        <span class="file-message tex">or drag and drop image</span>

        <input type="file" class="file-input" name="{{$name}}" @isset($src) data-old-image="{{$src}}" @endisset required/>
    </div>

    <div class="file-preview {{(isset($src)) ? 'flex' : 'hidden'}} justify-center">
        <div class="relative justify-center file-area dashed-border">
            <span class="image-clear-button interactive absolute top-1 right-1 pl-1">
                remove
            </span>

            <img class="img-frame img-fluid file-area p-1 max-w-full max-h-full" @isset($src) src="{{$src}}" @endisset/>
        </div>
    </div>
</div>
