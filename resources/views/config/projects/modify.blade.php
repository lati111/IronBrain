@extends('layouts.master')

@section('htmlTitle', 'Add Project')
@section('onloadFunction') datatableInit() @stop

@section('header')
<style>
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

{{--| delete modal |--}}
@component('components.delete_model')
    @slot('text') Are you sure you want to delete this project? @endslot
    @slot('confirmFunction') submit_stored_form() @endslot
@endcomponent



{{--| pagination form |--}}
<div class="flex justify-center">
    <div>
        {{--| title |--}}
        <div class="flex flex-row justify-center mb-3">
            <h3 class="title">
                @if(isset($project))
                    Modify Project
                @else
                    Add New Project
                @endif
            </h3>
        </div>

        {{--| form |--}}
        <div class="flex flex-row justify-center mb-3">
            <form id="form" action="{{ route('config.projects.save') }}" method="POST" enctype="multipart/form-data"  style="width: 620%">
                @csrf
                @isset($project)
                    <input type="hidden" name="id" value="{{$project->id}}">
                @endisset
                @if(old('id') !== null)
                    <input type="hidden" name="id" value="{{old('id')}}">
                @endif

                <div class="grid grid-cols-2 gap-4" style="width: 620px">
                    <div class="flex flex-col gap-4">
                        {{--| name field |--}}
                        <input type="text" name="name" class="smallInput underlined" placeholder="Name"
                            @isset($project) value="{{$project->name}}" @endisset
                            @if(old('name') !== null) value="{{old('name')}}" @endif
                        />

                        {{--| image uploader |--}}
                        <div class="flex flex-col justify-center">
                            <div class="flex justify-center">
                                <div id="fileUploader" class="file-drop-area file-area dashed-border
                                    @if(isset($project)) hidden @endif"
                                >
                                    <span class="choose-file-button interactive">Choose thumbnail</span>
                                    <span class="file-message">or drag and drop image</span>
                                    <input id="fileInput" class="file-input" type="file" name="thumbnail"
                                        @isset($project) old-thumbnail="{{$project->thumbnail}}" @endisset
                                        onchange="preview()" required
                                    />
                                </div>
                            </div>

                            <div class="flex justify-center">
                                @if ((isset($project) === true && $project->inOverview === true) || old('inOverview') === "on")
                                    <div id="filePreview"
                                        @if(!isset($project)) class="hidden @else class="flex @endif
                                        relative justify-center file-area dashed-border"
                                    />
                                        <span onclick="clearImage()" class="interactive absolute top-1 right-1 removeButton">remove</span>

                                        <span class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 disabledBlurb hidden">thumbnail is disabled</span>

                                        <img id="frame" class="img-fluid file-area p-1"
                                            @if(isset($project) && $project->thumbnail !== null)
                                                src="{{asset('img/project/thumbnail/'.$project->thumbnail)}}"
                                            @else
                                                src="data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs="
                                            @endif
                                        />
                                    </div>
                                @else
                                    <div id="filePreview"
                                        @if(!isset($project)) class="hidden @else class="flex @endif
                                        relative justify-center file-area dashed-border"
                                    />
                                        <span onclick="clearImage()" class="interactive absolute top-1 right-1 bg-white px-1 removeButton hidden">remove</span>

                                        <span class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 disabledBlurb">thumbnail is disabled</span>

                                        <img id="frame" class="img-fluid file-area p-1"
                                            @if(isset($project) && $project->thumbnail !== null)
                                                src="{{asset('img/project/thumbnail/'.$project->thumbnail)}}"
                                            @else
                                                src="data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs="
                                            @endif
                                        />
                                    </div>
                                @endif

                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col gap-4">
                        {{--| permission field |--}}
                        <select id="permissionSelect" name="permission" class="mediumInput underlined py-0">
                            <option value="">No permission</option>
                        </select>

                        {{--| route field |--}}
                        <input type="text" name="route" class="smallInput underlined" placeholder="Route" required
                            @isset($project) value="{{$project->route}}" @endisset
                            @if(old('route') !== null) value="{{old('route')}}" @endif
                        />

                        {{--| description field |--}}
                        <textarea name="description" class="mediumInput underlined"
                            style="height: 90px !important" placeholder="Description" required
                        >@if(isset($project)){{$project->description}}@elseif(old('description') !== null){{old('description')}}@endif</textarea>
                    </div>
                </div>

                <div class="flex flex-row justify-around mt-3 pl-6">
                    {{--| in project checkbox |--}}
                    <div class="flex items-center justify-center">
                        <input id="inOverviewCheckbox" type="checkbox" name="inOverview" class="w-4 h-4 text-red-600 focus:ring-red-500"
                            @if(old('inNav') === true)
                                checked
                            @elseif (isset($project) === true)
                                @if ($project->inOverview === 1) checked @endif
                            @else
                                checked
                            @endif
                            onchange="toggleThumbnailField()">
                        <label for="default-checkbox" class="ml-2 text-sm" name="inOverview">Visible in overview?</label>
                    </div>

                    {{--| in nav checkbox |--}}
                    <div class="flex items-center justify-center">
                        <input id="inNavCheckbox" type="checkbox" name="inNav" class="w-4 h-4 text-red-600 focus:ring-red-500" onchange="toggleOrderField()"
                            @isset($project) @if ($project->inNav === 1) checked @endif @endisset
                            @if(old('inNav') === true) checked @endif
                            />
                        <label for="default-checkbox" class="ml-2 text-sm" name="inNav">Visible in navigation?</label>
                    </div>

                    {{--| navigation order field |--}}
                    <div id="orderField" class="flex items-center justify-center gap-2 m-0
                        @if(isset($project))
                            @if ($project->inNav === false) invisible" @endif
                        @elseif(old('inNav') !== true)
                            invisible"
                        @endif

                    />
                        <label for="default-checkbox" class="ml-2 text-sm" name="order">Navigation order:</label>
                        <input type="number" name="order" class="w-16 h-4 pr-0 underlined"
                            @isset($project) value="{{$project->order}}" @endisset
                            @if(old('route') !== null) value="{{old('order')}}" @endif
                            />
                    </div>
                </div>


                {{--| submitter |--}}
                <div class="flex flex-col mt-3">
                    <input type="button" class="interactive" onclick="validate(this.closest('form'))" value="Save Project">
                </div>
            </form>
        </div>

        {{--| submenu table |--}}
        <div class="flex flex-row justify-center mb-3">
            @component('components.datatable.table')
                @slot('headers')
                    @component('components.datatable.header')
                        @slot('columnId')name @endslot
                        @slot('content')name @endslot
                    @endcomponent
                    @component('components.datatable.header')
                        @slot('columnId')route @endslot
                        @slot('content')route @endslot
                    @endcomponent
                    @component('components.datatable.header')
                        @slot('columnId')order @endslot
                        @slot('content')order @endslot
                    @endcomponent
                    @component('components.datatable.header')
                        @slot('columnId')actions @endslot
                        @slot('content')
                            <a href="{{route('config.projects.submenu.new', $project->id)}}" class="interactive no-underline">Add Project</a>
                        @endslot
                    @endcomponent
                @endslot
                @slot('dataUrl'){{route('config.projects.submenu.overview.datatable', $project->id)}} @endslot
            @endcomponent
        </div>
    </div>
</div>

@stop

@section('script')
@vite([
    'resources/ts/main.ts',
    'resources/ts/components/datatable.ts',
    'resources/ts/components/modal.ts'
])
<script>
    function validate() {
        if (form.querySelector('input[name="thumbnail"]').checkValidity() === false) {
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

    function toggleOrderField() {
        if (inNavCheckbox.checked) {
            orderField.classList.remove("invisible");
        } else {
            orderField.classList.add("invisible");
        }
    }

    function toggleThumbnailField() {
        if (inOverviewCheckbox.checked) {
            filePreview.classList.replace('flex', 'hidden');
            filePreview.classList.add('disabled');
            filePreview.querySelector(".disabledBlurb").classList.add('hidden')
            filePreview.querySelector(".removeButton").classList.remove('hidden')
            fileUploader.classList.remove('hidden')
        } else {
            fileUploader.classList.add('hidden')
            filePreview.classList.add('remove');
            filePreview.querySelector(".disabledBlurb").classList.remove('hidden')
            filePreview.querySelector(".removeButton").classList.add('hidden')
            filePreview.classList.replace('hidden', 'flex')
        }
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
