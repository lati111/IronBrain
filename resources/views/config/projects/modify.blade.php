@extends('layouts.form.multi_form_with_datatable')
@section('onloadFunctions')
    imageUploaderInit
    (
        'filePreview',
        'fileUploader',
        'frame',
        'inOverviewCheckbox'
    );
    fillSelectWithPermissions(
        'permissionSelect',
        @if(isset($project))
            @if($project->permission_id !== null)
                '{{$project->permission_id}}'
            @endif
        @endif
    );
@stop

@if(isset($project))
    @section('htmlTitle', 'Modify Project')
    @section('form_title', 'Modify Project')
    @section('submit_string', 'Save')
@else
    @section('htmlTitle', 'New Project')
    @section('form_title', 'New Project')
    @section('submit_string', 'New Project')
@endif

@section('submit_route', route('config.projects.save'))

@section('headers')
    @vite(['resources/css/components/form/components/image_uploader.css'])
@stop

{{--| top form |--}}
@section('form_content_top')
    {{--| id |--}}
    @isset($project)
        <input type="hidden" name="id" value="{{$project->id}}">
    @endisset
    @if(old('id') !== null)
        <input type="hidden" name="id" value="{{old('id')}}">
    @endif
@stop

{{--| left form |--}}
@section('form_content_left')
    {{--| name field |--}}
    @component('components.form.input_wrapper')
        @slot('label_text')Name  @endslot
        @slot('input_html')
            <input type="text" name="name" class="smallInput underlined" placeholder="Name"
                @isset($project) value="{{$project->name}}" @endisset
                @if(old('name') !== null) value="{{old('name')}}" @endif
            />
        @endslot
    @endcomponent

    {{--| image uploader |--}}
    @component('components.form.input_wrapper')
        @slot('label_text')Thumbnail  @endslot
        @slot('input_html')
            @component('components.form.image_uploader')
                @if(isset($project))
                    @isset($project->thumbnail)
                        @slot('thumbnail', $project->thumbnail)
                    @endisset
                @endif
                @if(isset($project))
                    @slot('isDisabled', !$project->in_overview)
                @else
                    @slot('isDisabled', false)
                @endif
            @endcomponent
        @endslot
    @endcomponent
@stop

{{--| right form |--}}
@section('form_content_right')
    {{--| permission field |--}}
    @component('components.form.input_wrapper')
        @slot('label_text')Permission @endslot
        @slot('input_html')
            @component('components.form.select.permission_list')
                @slot('classes', 'mediumInput')
                @slot('default_option', 'No permission needed')
            @endcomponent
        @endslot
    @endcomponent

    {{--| route field |--}}
    @component('components.form.input_wrapper')
        @slot('label_text')Route @endslot
        @slot('input_html')
            <input type="text" name="route" class="smallInput underlined" placeholder="Route" required
                @isset($project) value="{{$project->route}}" @endisset
                @if(old('route') !== null) value="{{old('route')}}" @endif
            />
        @endslot
    @endcomponent

    {{--| description field |--}}
    @component('components.form.input_wrapper')
        @slot('label_text')Description @endslot
        @slot('input_html')
            <textarea name="description" class="mediumInput underlined"
                style="height: 90px !important" placeholder="Description" required
            >@if(isset($project)){{$project->description}}@elseif(old('description') !== null){{old('description')}}@endif</textarea>
        @endslot
    @endcomponent
@stop

{{--| bottom form |--}}
@section('form_content_bottom')
    <div class="flex flex-row justify-around gap-4 pt-3">
        {{--| in project checkbox |--}}
        <div class="flex items-center justify-center">
            <input id="inOverviewCheckbox" type="checkbox" name="in_overview" class="w-4 h-4 text-red-600 focus:ring-red-500"
                @if(old('in_overview') === true)
                    checked
                @elseif (isset($project) === true)
                    @if ($project->in_overview === 1) checked @endif
                @else
                    checked
                @endif
                onchange="toggleThumbnailField()">
            <label for="default-checkbox" class="ml-2 text-sm" name="in_overview">Visible in overview?</label>
        </div>

        {{--| in nav checkbox |--}}
        <div class="flex items-center justify-center">
            <input id="inNavCheckbox" type="checkbox" name="in_nav" class="w-4 h-4 text-red-600 focus:ring-red-500" onchange="toggleOrderField()"
                @isset($project) @if ($project->in_nav === 1) checked @endif @endisset
                @if(old('in_nav') === true) checked @endif
                />
            <label for="default-checkbox" class="ml-2 text-sm" name="in_nav">Visible in navigation?</label>
        </div>

        {{--| navigation order field |--}}
        <div id="orderField" class="flex items-center justify-center gap-2 m-0
            @if(isset($project))
                @if ($project->in_nav === false) invisible" @endif
            @elseif(old('in_nav') !== true)
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
@stop

{{--| submenu table |--}}
@isset($project)
    @section('table_headers')
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
                <a href="{{route('config.projects.submenu.new', $project->id)}}" class="interactive no-underline">Add Submenu</a>
            @endslot
        @endcomponent
    @stop

    @section('datatable_url', route('config.projects.submenu.overview.datatable', $project->id))
@endisset

@section('scripts')
@vite([
    'resources/ts/components/form/image_uploader.ts',
    'resources/ts/components/form/permission_select.ts',
])
<script>
    function validate() {
        if (fileUploader.classList.contains('hidden') === false) {
            if (form.querySelector('input[name="thumbnail"]').checkValidity() === false) {
                return false;
            }
        }

        if (form.querySelector('input[name="name"]').checkValidity() === false) {
            return false;
        }

        if (form.querySelector('input[name="route"]').checkValidity() === false) {
            return false;
        }

        if (form.querySelector('select[name="permission_id"]').checkValidity() === false) {
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
</script>
@stop
