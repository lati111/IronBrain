@extends('layouts.master')

@section('header')
    @vite([
        'resources/css/components/datalist/cardlist.css'
    ])
    @yield('css')
@stop

@section('onloadFunction') cardlistInit() @stop

@section('content')
    @yield('before')
    @component('components.datalist.components.searchbar')
        @slot("dataproviderID", "pksanc-box-cardlist")
        @slot("searchfields", "nickname,pokemon")
    @endcomponent
    <div class="flex justify-center mt-4">
        <div>
            {{--| cardlist |--}}
            <div class="flex flex-row justify-center mb-3">
                @component('components.datalist.cardlist.list')
                    @slot("ID", "pksanc-box-cardlist")
                    @slot('dataUrl') @yield('url') @endslot
                @endcomponent
            </div>
        </div>
    </div>
    @component('components.datalist.components.pagination')
        @slot("dataproviderID", "pksanc-box-cardlist")
    @endcomponent

    @yield('after')
@stop

@section('script')
@yield('scripts')
@vite([
    'resources/ts/main.ts',
    'resources/ts/components/cardlist.ts',
])
@stop
