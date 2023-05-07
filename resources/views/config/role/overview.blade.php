@extends('layouts.datatable.overview')
@section('htmlTitle', 'Role Overview')
@section('table_title', 'Role Overview')

@section('delete_modal_text', 'Are you sure you want to delete this role?')

{{--| table |--}}
@section('headers')
    @component('components.datatable.header')
        @slot('columnId')name @endslot
        @slot('content')Name @endslot
    @endcomponent
    @component('components.datatable.header')
        @slot('columnId')description @endslot
        @slot('content')Description @endslot
    @endcomponent
    @component('components.datatable.header')
        @slot('columnId')actions @endslot
        @slot('content')
            <a href="{{route('config.role.new')}}" class="interactive no-underline">Add Role</a>
        @endslot
    @endcomponent
@endsection

@section('delete_modal_text', 'Are you sure you want to deactivate this user?')
@section('datatable_url', route('config.role.overview.datatable'))
