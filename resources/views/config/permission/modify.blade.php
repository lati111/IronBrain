@extends('layouts.form.single_form')

@if(isset($permission))
    @section('htmlTitle', 'Save Permission')
    @section('form_title', 'Save Permission')
    @section('submit_string', 'Save')
@else
    @section('htmlTitle', 'Add Permission')
    @section('form_title', 'Add Permission')
    @section('submit_string', 'Add Permission')
@endif

@section('form_content')
    @isset($permission)
        <input type="hidden" name="id" value="{{$permission->id}}">
    @endisset

    {{--| name field |--}}
    @component('components.form.input-wrapper')
        @slot('label_text')
            Name
        @endslot
        @slot('input_html')
            <input type="text" name="name" class="largeInput underlined" placeholder="Name"
                   @isset($permission) value="{{$permission->name}}" @endisset
                   @if(old('name') !== null) value="{{old('name')}}" @endif
                   dusk="name_input" required
            />
        @endslot
    @endcomponent

    {{--| permission field |--}}
    @component('components.form.input-wrapper')
        @slot('label_text')
            Permission
        @endslot
        @slot('input_html')
            <input type="text" name="permission" class="largeInput underlined" placeholder="Permission"
                   @isset($permission) value="{{$permission->permission}}" @endisset
                   @if(old('permission') !== null) value="{{old('permission')}}" @endif
                   dusk="permission_input" required
            />
        @endslot
    @endcomponent

    {{--| group field |--}}
    @component('components.form.input-wrapper')
        @slot('label_text')
            Group
        @endslot
        @slot('input_html')
            <input type="text" name="group" class="largeInput underlined" placeholder="Group"
                   @isset($permission) value="{{$permission->group}}" @endisset
                   @if(old('group') !== null) value="{{old('group')}}" @endif
                   dusk="group_input" required
            />
        @endslot
    @endcomponent

    {{--| description field |--}}
    @component('components.form.input-wrapper')
        @slot('label_text')
            Description
        @endslot
        @slot('input_html')
            <textarea name="description" class="largeInput underlined"
                      style="height: 90px !important" placeholder="Description" dusk="description_input" required
            >@if(isset($permission))
                    {{$permission->description}}
                @elseif(old('description') !== null)
                    {{old('description')}}
                @endif</textarea>
        @endslot
    @endcomponent
@stop

@section('submit_route', route('config.permission.save'))




