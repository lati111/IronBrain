@extends('layouts.master')

@section('header')
    @vite([
        'resources/css/components/datalist/cardlist.css'
    ])
@stop

@section('onloadFunction') cardlistInit() @stop

@section('content')
    @yield('before')

    <div class="flex justify-center">
        <div>
            {{--| cardlist |--}}
            <div class="flex flex-row justify-center mb-3">
                @component('components.datalist.cardlist.list')
                    @slot('dataUrl') @yield('url') @endslot
                @endcomponent
            </div>
        </div>
    </div>

    @yield('after')
@stop

@section('script')
@yield('scripts')
@vite([
    'resources/ts/main.ts',
    'resources/ts/components/cardlist.ts',
])
@stop
