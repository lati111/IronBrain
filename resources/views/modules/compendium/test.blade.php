@extends('layouts.master')

@section('htmlTitle', 'Test')
@section('title', 'Test')

@section('onloadFunction', 'test()')

@section('header')
    @vite(['resources/ts/modules/compendium/core.ts'])
@endsection

@section('content')
    <div id="result">

    </div>
@stop
