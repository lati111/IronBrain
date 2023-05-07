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
    <div>
        <div class="text-sm ml-3 form_label">Name</div>
        <input type="text" name="name" class="largeInput underlined" placeholder="Name"
            @isset($permission) value="{{$permission->name}}" @endisset
            @if(old('name') !== null) value="{{old('name')}}" @endif
            required
        />
    </div>

    {{--| permission field |--}}
    <div>
        <div class="text-sm ml-3 form_label">Permission</div>
        <input type="text" name="permission" class="largeInput underlined" placeholder="Permission"
            @isset($permission) value="{{$permission->permission}}" @endisset
            @if(old('permission') !== null) value="{{old('permission')}}" @endif
            required
        />
    </div>

    {{--| group field |--}}
    <div>
        <div class="text-sm ml-3 form_label">Group</div>
        <input type="text" name="group" class="largeInput underlined" placeholder="Group"
            @isset($permission) value="{{$permission->group}}" @endisset
            @if(old('group') !== null) value="{{old('group')}}" @endif
            required
        />
    </div>

    {{--| description field |--}}
    <div>
        <div class="text-sm ml-3 form_label">Description</div>
        <textarea name="description" class="largeInput underlined"
            style="height: 90px !important" placeholder="Description" required
        >@if(isset($permission)){{$permission->description}}@elseif(old('description') !== null){{old('description')}}@endif</textarea>
    </div>
@stop

@section('submit_route', route('config.permission.save'))




