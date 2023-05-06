@extends('layouts.form.single_form')

@if(isset($permission))
    @section('htmlTitle', 'Save Permission')
    @section('form_title', 'Save Permission')
    @section('submit_string', 'Save Permission')
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
    <input type="text" name="name" class="largeInput underlined" placeholder="Name"
        @isset($permission) value="{{$permission->name}}" @endisset
        @if(old('name') !== null) value="{{old('name')}}" @endif
        required
    />

    {{--| group field |--}}
    <input type="text" name="group" class="largeInput underlined" placeholder="Group"
        @isset($permission) value="{{$permission->group}}" @endisset
        @if(old('group') !== null) value="{{old('group')}}" @endif
        required
    />

    {{--| description field |--}}
    <textarea name="description" class="largeInput underlined"
        style="height: 90px !important" placeholder="Description" required
    >@if(isset($permission)){{$permission->description}}@elseif(old('description') !== null){{old('description')}}@endif</textarea>
@stop

@section('submit_route', route('config.permission.save'))




