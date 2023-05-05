@extends('layouts.master')

@section('htmlTitle', 'Add nav')

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

<div class="flex justify-center">
    <div>
        {{--| title |--}}
        <div class="flex flex-row justify-center mb-3">
            <h3 class="title">
                @if(isset($submenu))
                    Modify Submenu Item
                @else
                    Add New Submenu Item
                @endif
            </h3>
        </div>

        {{--| form |--}}
        <div class="flex flex-row justify-center mb-3">
            <form id="form" action="{{ route('config.nav.save') }}" method="POST" enctype="multipart/form-data"  style="width: 620%">
                @csrf
                    <input type="hidden" name="navId" value="{{$projectId}}">

                    <div class="flex flex-col gap-4">
                        {{--| name field |--}}
                        <div class="flex justify-center gap-4">
                            <label for="name"  class="w-16">Name: </label>
                            <input type="text" name="name" class="smallInput underlined" placeholder="Name"
                                @isset($submenu) value="{{$submenu->name}}" @endisset
                                @if(old('name') === null) value="{{old('name')}}" @endif
                            />
                        </div>

                        {{--| route field |--}}
                        <div class="flex justify-center gap-4">
                            <label for="name"  class="w-16">Name: </label>
                            <input type="text" name="route" class="smallInput underlined" placeholder="Route" required
                                @isset($submenu) value="{{$submenu->route}}" @endisset
                                @if(old('route') === null) value="{{old('route')}}" @endif
                            />
                        </div>

                        {{--| permission field |--}}
                        <div class="flex justify-center gap-4">
                            <label for="permission"  class="w-16">Permission: </label>
                            <select id="permissionSelect" name="permission" class="mediumInput underlined py-0">
                                <option value="">No permission needed</option>
                            </select>
                        </div>


                        {{--| order field |--}}
                        <div class="flex justify-center gap-4">
                            <label for="order"  class="w-16">Order: </label>
                            <input type="number" name="order" class="smallInput underlined" placeholder="0..."
                                @isset($submenu) value="{{$submenu->order}}" @endisset
                                @if(old('name') === null) value="{{old('name')}}" @endif
                            />
                        </div>
                    </div>

                {{--| submitter |--}}
                <div class="flex flex-col mt-3">
                    <input type="submit" class="interactive" value="Save Submenu">
                </div>
            </form>
        </div>
    </div>
</div>

@stop

@section('script')
<script>
    function validate() {
        if (fileUploader.classList.contains('hidden') === true) {
            if (form.querySelector('input[name="thumbnail"]').hasAttribute('old-thumbnail') === false) {
                // clearImage();
            }
        } else if (form.querySelector('input[name="thumbnail"]').checkValidity() === false) {
            return false;
        }

        if (form.querySelector('input[name="name"]').checkValidity() === false) {
            return false;
        }

        if (form.querySelector('input[name="route"]').checkValidity() === false) {
            return false;
        }

        if (form.querySelector('select[name="permission"]').checkValidity() === false) {
            return false;
        }

        if (form.querySelector('textarea[name="description"]').checkValidity() === false) {
            return false;
        }

        form.submit();
    }

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
