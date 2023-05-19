@extends('layouts.datatable.overview')
@section('htmlTitle', 'Project Overview')
@section('table_title', 'Project Overview')

@section('delete_modal_text', 'Are you sure you want to delete this permission?')

{{--| table |--}}
@section('headers')
    @section('table-size', 'middle')
    @component('components.datatable.header')
        @slot('columnId')thumbnail @endslot
        @slot('content')Thumbnail @endslot
    @endcomponent
    @component('components.datatable.header')
        @slot('columnId')name @endslot
        @slot('content')Name @endslot
    @endcomponent
    @component('components.datatable.header')
        @slot('columnId')description @endslot
        @slot('content')Description @endslot
    @endcomponent
    @component('components.datatable.header')
        @slot('columnId')route @endslot
        @slot('content')Route @endslot
    @endcomponent
    @component('components.datatable.header')
    @slot('columnId')order @endslot
    @slot('content')Nav Order @endslot
@endcomponent
    @component('components.datatable.header')
        @slot('columnId')actions @endslot
        @slot('content')
        <a href="{{route('config.projects.new')}}" class="interactive no-underline" dusk="add_project">Add Project</a>
        @endslot
    @endcomponent
@endsection

@section('delete_modal_text', 'Are you sure you want to delete this project?')
@section('datatable_url', route('config.project.overview.datatable'))
