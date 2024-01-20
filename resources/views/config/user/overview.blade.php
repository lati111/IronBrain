@extends('layouts.datalist.datatable.overview')
@section('htmlTitle', 'Role Overview')
@section('table_title', 'Role Overview')

@section('delete_modal_text', 'Are you sure you want to deactivate this user?')

{{--| table |--}}
@section('table-size', 'middle')
@section('headers')
    @component('components.datalist.datatable.header')
        @slot('columnId')profile_picture @endslot
        @slot('content')Profile picture @endslot
    @endcomponent
    @component('components.datalist.datatable.header')
        @slot('columnId')name @endslot
        @slot('content')Name @endslot
    @endcomponent
    @component('components.datalist.datatable.header')
        @slot('columnId')email @endslot
        @slot('content')Email @endslot
    @endcomponent
    @component('components.datalist.datatable.header')
        @slot('columnId')role @endslot
        @slot('content')Role @endslot
    @endcomponent
    @component('components.datalist.datatable.header')
        @slot('columnId')actions @endslot
        @slot('content')Actions @endslot
    @endcomponent
@endsection

@section('delete_modal_text', 'Are you sure you want to deactivate this user?')
@section('datatable_url', route('config.user.overview.datatable'))

{{--| modals |--}}
@section('modals')
    @component('components.modal.select_modal')
        @slot('id')role_modal @endslot
        @slot('name')role_id @endslot
        @slot('title')Select a Role @endslot
        @slot('options')
            <option value="">Default</option>
            @foreach ($roles as $role)
                <option value="{{$role->id}}">{{$role->name}}</option>
            @endforeach
        @endslot
        @slot('submit_function') submit_stored_form(true) @endslot
    @endcomponent
@endsection
