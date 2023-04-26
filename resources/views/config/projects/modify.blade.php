@extends('layouts.master')

@section('htmlTitle', 'Add Project')

@section('header')
<style>
    .smallInput {
        height: 24px;
        width: 300px
    }

    .mediumInput {
        height: 24px;
        width: 300px;
    }

    .dashed-border {
        border: 1px dashed var(--main-grey);
        border-radius: 3px;
    }

    .file-drop-area {
        position: relative;
        display: flex;
        align-items: center;
        max-width: 100%;
        padding: 25px;
        transition: 0.2s;
    }

    .choose-file-button {
        flex-shrink: 0;
        color: var(--main-red);
        border-radius: 3px;
        padding: 8px 2px;
        margin-right: 10px;
        font-size: 12px;
        text-transform: uppercase;
    }

    .file-message {
        font-size: small;
        font-weight: 300;
        line-height: 1.4;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .file-input {
        position: absolute;
        left: 0;
        top: 0;
        height: 100%;
        width: 100%;
        cursor: pointer;
        opacity: 0;
    }

    .file-area {
        height: 140px !important;
        width: 300px !important;
    }

    .hidden {
        display: none !important;
    }
</style>
@stop

@section('content')

{{--| pagination form |--}}
<div class="flex justify-center">
    <div>
        {{--| title |--}}
        <div class="flex flex-row justify-center mb-3">
            <h3 class="title">
                Add New Project
            </h3>
        </div>

        {{--| form |--}}
        <form id="form" action="{{ route('config.projects.overview') }}" method="POST" enctype="multipart/form-data  style="width: 75%">
            @isset($id)
                <input type="hidden" name="id" value="{{$id}}">
            @endisset

            <div class="grid grid-cols-2 gap-4">
                <div class="flex flex-col gap-4">
                    {{--| name field |--}}
                    <input type="text" name="name" class="smallInput underlined" placeholder="Name" required>

                    {{--| image uploader |--}}
                    <div class="flex flex-col justify-center">
                        <div class="flex justify-center">
                            <div id="fileUploader" class="file-drop-area file-area dashed-border">
                                <span class="choose-file-button interactive">Choose thumbnail</span>
                                <span class="file-message">or drag and drop image</span>
                                <input id="fileInput" class="file-input" type="file" name="thumbnail" onchange="preview()" required>
                            </div>
                        </div>

                        <div class="flex justify-center">
                            <div id="filePreview" class="relative justify-center file-area dashed-border hidden">
                                <span onclick="clearImage()" class="interactive absolute top-1 right-1">remove</span>
                                <img id="frame" src="{{asset('img/project/sprites/pksanc.jpg')}}" class="img-fluid file-area p-1 " />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-4">
                    {{--| permission field |--}}
                    <select id="permissionSelect" name="permission" class="mediumInput underlined py-0">
                        <option value="">No permission</option>
                    </select>

                    {{--| route field |--}}
                    <input type="text" name="route" class="smallInput underlined" placeholder="Route" required>

                    {{--| description field |--}}
                    <textarea name="description" class="mediumInput underlined"
                        style="height: 90px !important" placeholder="Description" required
                    /></textarea>
                </div>
            </div>

            {{--| submitter |--}}
            <div class="flex flex-col mt-3">
                <input type="submit" class="interactive" value="Save Project">
            </div>
        </form>
    </div>
</div>

@stop

@section('script')
<script>
    function preview() {
        frame.src = URL.createObjectURL(event.target.files[0]);
        fileUploader.classList.add('hidden')
        filePreview.classList.replace('hidden', 'flex')
    }

    function clearImage() {
        document.getElementById('fileInput').value = null;
        filePreview.classList.replace('flex', 'hidden');
        fileUploader.classList.remove('hidden')
        frame.src = "";
    }
</script>
@stop
