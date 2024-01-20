@extends(isset($role) ? 'layouts.form.single_form_with_datatable' : 'layouts.form.single_form')

@if(isset($role))
    @section('htmlTitle', 'Save Role')
    @section('form_title', 'Save Role')
    @section('submit_string', 'Save Role')
@else
    @section('htmlTitle', 'Add Role')
    @section('form_title', 'Add Role')
    @section('submit_string', 'Add Role')
@endif

@section('form_content')
    @isset($role)
        <input type="hidden" name="id" value="{{$role->id}}">
    @endisset

    {{--| name field |--}}
    <input type="text" name="name" class="largeInput underlined" placeholder="Name"
        @isset($role) value="{{$role->name}}" @endisset
        @if(old('name') !== null) value="{{old('name')}}" @endif
        required
    />

    {{--| description field |--}}
    <textarea name="description" class="largeInput underlined"
        style="height: 90px !important" placeholder="Description" required
    >@if(isset($role)){{$role->description}}@elseif(old('description') !== null){{old('description')}}@endif</textarea>
@stop

{{--| permission table |--}}
@if(isset($role))
    @section('headers')
        @component('components.datalist.datatable.header')
            @slot('columnId')has_permission @endslot
            @slot('content') @endslot
        @endcomponent
        @component('components.datalist.datatable.header')
            @slot('columnId')permission @endslot
            @slot('content')Permission @endslot
        @endcomponent
        @component('components.datalist.datatable.header')
            @slot('columnId')description @endslot
            @slot('content')Description @endslot
        @endcomponent
    @stop

    @section('datatable_url', route('config.role.permission.datatable', $role->id))
@endif

@section('submit_route', route('config.role.save'))

@section('scripts')
    @vite(['resources/ts/datatable/permission_toggle.ts'])
@stop
