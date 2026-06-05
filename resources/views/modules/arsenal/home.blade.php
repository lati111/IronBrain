@extends('layouts.main')

@section('htmlTitle', 'Arsenal')
@section('title', 'Arsenal')

@section('header')
    @vite([
        'resources/css/components/datalist/cardlist.css',
    ])
@stop

@section('onload_functions', 'init()')

@section('content')

@endsection
