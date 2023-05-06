@extends('layouts.master')

@section('htmlTitle', 'Add Project')
@section('onloadFunction') datatableInit() @stop

@section('header')
<style>
    .file-drop-area {
        position: relative;
        display: flex;
        align-items: center;
        max-width: 100%;
        padding: 25px;
        transition: 0.2s;
    }

    .choose-file-button {
        flex-shrink: 0;
        color: var(--main-red);
        border-radius: 3px;
        padding: 8px 2px;
        margin-right: 10px;
        font-size: 12px;
        text-transform: uppercase;
    }

    .file-message {
        font-size: small;
        font-weight: 300;
        line-height: 1.4;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .file-input {
        position: absolute;
        left: 0;
        top: 0;
        height: 100%;
        width: 100%;
        cursor: pointer;
        opacity: 0;
    }

    .file-area {
        height: 140px !important;
        width: 300px !important;
    }
</style>
@stop


@section('content')

{{--| delete modal |--}}
@component('components.delete_model')
    @slot('text') Are you sure you want to delete this project? @endslot
    @slot('confirmFunction') submit_stored_form() @endslot
@endcomponent


{{--| pagination form |--}}
<div class="flex justify-center">
    <div>
        {{--| title |--}}
        <div class="flex flex-row justify-center mb-3">
            <h3 class="title">
                Permission Overview
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
                        @slot('columnId')group @endslot
                        @slot('content')Group @endslot
                    @endcomponent
                    @component('components.datatable.header')
                        @slot('columnId')actions @endslot
                        @slot('content')
                            <a href="{{route('config.permission.new')}}" class="interactive no-underline">Add Permission</a>
                        @endslot
                    @endcomponent
                @endslot
                @slot('dataUrl')# @endslot
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
