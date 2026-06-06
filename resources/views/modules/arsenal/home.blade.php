@extends('layouts.main')

@section('htmlTitle', 'Arsenal')
@section('title', 'Arsenal')

@section('header')
    @vite([
        'resources/css/components/datalist/cardlist.css',
        'resources/css/modules/arsenal/home.css',
        'resources/ts/modules/arsenal/home.ts',
    ])
@stop

@section('onload_functions', 'init()')

@section('content')
    <div class="mt-6 pb-4"></div>

    <div class="flex flex-row justify-center mb-3">
        <x-datalist.cardlist.list id="loadout-cardlist" url="{{route('data.arsenal.loadouts')}}">
            @include('modules.arsenal.snippits.loadout-cardlist-template')
        </x-datalist.cardlist.list>
    </div>
@endsection
