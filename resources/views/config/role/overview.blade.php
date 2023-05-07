@extends('layouts.master')

@section('htmlTitle', 'Role Overview')
@section('onloadFunction') datatableInit() @stop

@section('header')
@stop


@section('content')

{{--| delete modal |--}}
@component('components.delete_model')
    @slot('text') Are you sure you want to delete this project? @endslot
    @slot('confirmFunction') submit_stored_form() @endslot
@endcomponent


<div class="flex justify-center">
    <div>
        {{--| title |--}}
        <div class="flex flex-row justify-center mb-3">
            <h3 class="title">
                Role Overview
            </h3>
        </div>

        {{--| submenu table |--}}
        <div class="flex flex-row justify-center mb-3">
            @component('components.datatable.table')
                @slot('headers')
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
                @endslot
                @slot('dataUrl'){{route('config.role.overview.datatable')}} @endslot
            @endcomponent
        </div>
    </div>
</div>

@stop

@section('script')
@vite([
    'resources/ts/main.ts',
    'resources/ts/components/datatable.ts',
])
@stop
