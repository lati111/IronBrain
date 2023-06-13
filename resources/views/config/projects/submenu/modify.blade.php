@extends('layouts.form.single_form')

@if(isset($submenu))
    @section('htmlTitle', 'Modify Submenu')
    @section('form_title', 'Modify Submenu')
    @section('submit_string', 'Save')
@else
    @section('htmlTitle', 'Add Submenu')
    @section('form_title', 'Add Submenu')
    @section('submit_string', 'Add Submenu')
@endif

@section('onloadFunction')
    fillSelectWithPermissions(
        'permissionSelect',
        @if(isset($submenu))
            @if($submenu->permission_id !== null)
                '{{$submenu->permission_id}}'
            @endif
        @endif
    );
@stop

@section('form_content')
    @isset($submenu)
        <input type="hidden" name="id" value="{{$submenu->id}}">
    @endisset
    @if(old('id') !== null)
        <input type="hidden" name="id" value="{{old('id')}}">
    @endif

    {{--| name field |--}}
    @component('components.form.input_wrapper')
        @slot('label_text')Name @endslot
        @slot('input_html')
            <input type="text" name="name" class="largeInput underlined" placeholder="Name"
                @isset($submenu) value="{{$submenu->name}}" @endisset
                @if(old('name') !== null) value="{{old('name')}}" @endif
                dusk="name_input" required
            />
        @endslot
    @endcomponent

    {{--| route field |--}}
    @component('components.form.input_wrapper')
        @slot('label_text')Password @endslot
        @slot('input_html')
            <input type="text" name="route" class="largeInput underlined" placeholder="Route"
                @isset($submenu) value="{{$submenu->route}}" @endisset
                @if(old('route') !== null) value="{{old('route')}}" @endif
                dusk="route_input" required
            />
        @endslot
    @endcomponent

    {{--| permission field |--}}
    @component('components.form.input_wrapper')
        @slot('label_text')Permission @endslot
        @slot('input_html')
            @component('components.form.select.permission_list')
                @slot('classes', 'largeInput')
                @slot('default_option', 'No permission needed')
            @endcomponent
        @endslot
    @endcomponent

    {{--| navigation order field |--}}
    <div class="flex flex-row justify-center">
        @component('components.form.input_wrapper')
            @slot('label_text')Order @endslot
            @slot('input_html')
                <input type="number" name="order" class="w-16 h-4 pr-0 underlined"
                    @isset($submenu) value="{{$submenu->order}}" @endisset
                    @if(old('route') !== null) value="{{old('order')}}" @endif
                    dusk="order_input" required
                />
            @endslot
        @endcomponent
    </div>
@stop

@section('scripts')
    @vite(['resources/ts/components/form/permission_select.ts'])
@stop

@section('submit_route', route('config.projects.submenu.save', $project_id))
