@extends('layouts.master')

@section('onloadFunction') datatableInit(); @yield('onloadFunction') @stop
@section('header')@yield('headers') @stop

@section('content')

{{--| delete modal |--}}
@component('components.modal.delete_modal')
    @slot('text') Are you sure you want to delete this project? @endslot
    @slot('confirmFunction') submit_stored_form() @endslot
@endcomponent

<div class="flex justify-center">
    <div>
        {{--| title |--}}
        <div class="flex flex-row justify-center mb-3">
            <h3 class="title">
                @yield('form_title')
            </h3>
        </div>

        {{--| form |--}}
        <div class="flex flex-row justify-center mb-3">
            <form id="form" action="@yield('submit_route')" method="POST" enctype="multipart/form-data">
                @csrf

                <div class="flex flex-col gap-4">
                    @yield('form_content_top')
                </div>

                <div class="grid grid-cols-2 gap-4" style="width: 620px">
                    <div class="flex flex-col gap-4">
                        @yield('form_content_left')
                    </div>
                    <div class="flex flex-col gap-4">
                        @yield('form_content_right')
                    </div>
                </div>

                <div class="flex flex-col gap-4">
                    @yield('form_content_bottom')
                </div>

                {{--| submitter |--}}
                <div class="flex flex-col mt-3">
                    <input type="submit" class="interactive" value="@yield('submit_string')">
                </div>
            </form>
        </div>

        {{--| data table |--}}
        <div class="flex flex-row justify-center mb-3">
            @component('components.datatable.table')
                @slot('headers')
                    @yield('table_headers')
                @endslot
                @slot('dataUrl')@yield('datatable_url') @endslot
            @endcomponent
        </div>
    </div>
</div>

@stop

@section('script')
    @yield('scripts')
    @vite([
        'resources/ts/main.ts',
        'resources/ts/components/datatable.ts',
        'resources/ts/components/modal.ts'
    ])
@stop
