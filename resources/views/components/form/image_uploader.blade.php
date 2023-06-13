<div class="flex justify-center">
    <div id="fileUploader" class="file-drop-area file-area dashed-border
        @if(isset($thumbnail) === true || $isDisabled === true) hidden @endif"
    >
        <span class="choose-file-button interactive">Choose thumbnail</span>
        <span class="file-message">or drag and drop image</span>
        <input id="fileInput" class="file-input" type="file" name="thumbnail"
            @isset($thumbnail) old-thumbnail="{{$thumbnail}}" @endisset
            onchange="preview(event)" dusk="image_uploader" required
        />
    </div>
</div>

<div class="flex justify-center">
        <div id="filePreview"
            @if(isset($thumbnail) === false && $isDisabled === false) class="hidden @else class="flex @endif
            relative justify-center file-area dashed-border"
        />
            <span onclick="clearImage()" class="interactive absolute top-1 right-1 removeButton
                @if($isDisabled === true)
                    bg-white px-1
                @else
                    hidden
                @endif"
            />remove</span>

            <span class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 disabledBlurb
                @if($isDisabled === false) hidden @endif">thumbnail is disabled</span>

            <img id="frame" class="img-fluid file-area p-1"
                @if(isset($thumbnail) === true && $isDisabled === false)
                    src="{{asset('img/project/thumbnail/'.$thumbnail)}}"
                @else
                    src="data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs="
                @endif
            />
        </div>
</div>
