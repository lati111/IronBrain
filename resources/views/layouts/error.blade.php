@extends('layouts.master')

@section('content')
    <div class="flex justify-center">
        <div class="text-center">
            <h1 class="title">@yield('errorTitle')</h1>
            <div>@yield('message')</div>
        </div>
    </div>
@stop
