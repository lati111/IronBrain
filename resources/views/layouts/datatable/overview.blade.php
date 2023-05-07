@extends('layouts.master')

@section('onloadFunction') datatableInit() @stop

@section('content')

{{--| modals |--}}
@hasSection('delete_modal_text')
    @component('components.modal.delete_modal')
        @slot('text')@yield('delete_modal_text') @endslot
        @slot('confirmFunction') submit_stored_form() @endslot
    @endcomponent
@endif

@yield('modals')

<div class="flex justify-center">
    <div>
        {{--| title |--}}
        <div class="flex flex-row justify-center mb-3">
            <h3 class="title">
                @yield('table_title')
            </h3>
        </div>

        {{--| table |--}}
        <div class="flex flex-row justify-center mb-3">
            @component('components.datatable.table')
                @slot('headers')@yield('headers') @endslot
                @hasSection('table-size')
                    @slot('table_size') @yield('table-size') @endslot
                @endif
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
    'resources/ts/components/modal.ts',
])
@stop
