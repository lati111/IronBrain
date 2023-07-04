@extends('layouts.master')

@section('onloadFunction') datatableInit(); @yield('onloadFunctions') @stop
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
        <div class="flex flex-row justify-center mb-3" dusk="multi-form">
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
                    @hasSection('submit_function')
                        <input id="submitter" type="button" class="interactive" value="@yield('submit_string')" onclick="@yield('submit_function')" dusk="submitter">
                    @else
                        <input id="submitter" type="submit" class="interactive" value="@yield('submit_string')" dusk="submitter">
                    @endif
                </div>
            </form>
        </div>

        {{--| data table |--}}
        <div class="flex flex-row justify-center mb-3">
            @hasSection('datatable_url')
                @component('components.datalist.datatable.table')
                    @slot('headers')
                        @yield('table_headers')
                    @endslot
                    @slot('dataUrl')@yield('datatable_url') @endslot
                @endcomponent
            @endif
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
