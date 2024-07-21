@extends('layouts.main')

@section('onload_functions') init() @stop

@section('content')
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
                @yield('table')
            </div>
        </div>
    </div>
@stop
