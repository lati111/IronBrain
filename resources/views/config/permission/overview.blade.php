@extends('layouts.datalist.datatable.overview')
@section('htmlTitle', 'Permission Overview')
@section('table_title', 'Permission Overview')

@section('delete_modal_text', 'Are you sure you want to delete this permission?')

{{--| table |--}}
@section('headers')
@component('components.datalist.datatable.header')
        @slot('columnId')permission @endslot
        @slot('content')Permission @endslot
    @endcomponent
    @component('components.datalist.datatable.header')
        @slot('columnId')name @endslot
        @slot('content')Name @endslot
    @endcomponent
    @component('components.datalist.datatable.header')
        @slot('columnId')description @endslot
        @slot('content')Description @endslot
    @endcomponent
    @component('components.datalist.datatable.header')
        @slot('columnId')group @endslot
        @slot('content')Group @endslot
    @endcomponent
    @component('components.datalist.datatable.header')
        @slot('columnId')actions @endslot
        @slot('content')
            <a href="{{route('config.permission.new')}}" class="interactive no-underline" dusk="new_permission">Add Permission</a>
        @endslot
    @endcomponent
@endsection

@section('delete_modal_text', 'Are you sure you want to deactivate this user?')
@section('datatable_url', route('config.permission.overview.datatable'))
