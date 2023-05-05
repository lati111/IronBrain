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
                @if(isset($nav))
                    Modify Navigation Item
                @else
                    Add New Navigation Item
                @endif
            </h3>
        </div>

        {{--| form |--}}
        <div class="flex flex-row justify-center mb-3">
            <form id="form" action="{{ route('config.nav.save') }}" method="POST" enctype="multipart/form-data"  style="width: 620%">
                @csrf
                @isset($nav)
                    <input type="hidden" name="id" value="{{$nav->id}}">
                @endisset
                @if(old('id') !== null)
                    <input type="hidden" name="id" value="{{old('id')}}">
                @endif

                    <div class="flex flex-col gap-4">
                        {{--| project field |--}}
                        <div class="flex justify-center gap-4">
                            <label for="projectId" class="w-16">Project: </label>
                            <select id="projectSelect" name="projectId" class="mediumInput underlined py-0">
                                <option>Please select a project</option>
                                @foreach ($projects as $project)
                                    <option value="{{$project->id}}"
                                        @isset($nav) @if($project->id === $nav->projectId) selected @endif @endisset
                                        @if($project->id === old('projectId')) selected @endif
                                        />{{$project->name}}</option>
                                @endforeach
                            </select>
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
                                @isset($nav) value="{{$nav->order}}" @endisset
                                @if(old('name') === null) value="{{old('name')}}" @endif
                            />
                        </div>
                    </div>

                {{--| submitter |--}}
                <div class="flex flex-col mt-3">
                    <input type="submit" class="interactive" value="Save Nav">
                </div>
            </form>
        </div>


        {{--| submenu table |--}}
        <div class="flex flex-row justify-center mb-3 w-full">
            @isset($submenus)
            <table class="table table-hover table-striped">
                <thead>
                    <tr>
                        <th scope="col" class="text-center">Name</th>
                        <th scope="col" class="text-center">Route</th>
                        <th scope="col" class="text-center">Permission</th>
                        <th scope="col" class="text-center">Order</th>
                        <th scope="col" class="text-center">
                            <a href="{{route('config.submenu.new', $nav->projectId)}}" class="interactive no-underline">Add Navigation</a>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($submenus as $submenu)
                    <tr class="">
                        <td class="text-center">{{$submenu["name"]}}</td>
                        <td class="text-center">{{$submenu["description"]}}</td>
                        <td class="text-center">{{$submenu["route"]}}</td>
                        <td>
                            <div class="text-center">
                                <a href="{{route("config.nav.modify", $submenu["id"])}}" class="interactive">edit</a>
                            </div>
                            <div class="text-center">
                                <form action="{{route("config.nav.delete", $submenu["id"])}}" method="POST">
                                    @csrf
                                    <span
                                        onclick="store_form(this.closest('form'))" class="interactive"
                                        data-modal-target="delete_modal" data-modal-toggle="delete_modal"
                                        />delete</span>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endisset
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
