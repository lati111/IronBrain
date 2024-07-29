@extends('layouts.main')

@section('htmlTitle', 'PKSanc Box')
@section('title', 'Box')

@section('header')
    @vite([
        'resources/css/components/datalist/cardlist.css',
        'resources/css/modules/pksanc/box.css',
        'resources/ts/modules/pksanc/box.ts'
    ])
@stop

@section('onload_functions', 'init()')

@section('content')
    {{--| title |--}}
    <div class="flex justify-center">
        <div id="top-bar-container" class="relative">
            <a href="{{route('pksanc.pokedex.show')}}" class="interactive">Pokedex</a>
            <a href="{{route('pksanc.deposit.show')}}" class="interactive absolute right-0 top-0">Deposit Pokemon</a>
        </div>
    </div>

    <div class="mt-6 pb-4"></div>

    {{--| cardlist |--}}
    <div class="flex flex-row justify-center mb-3" dusk="form">
        <x-datalist.cardlist.list id="pokemon-cardlist" url="{{route('pksanc.overview.cardlist')}}" filtering="true">
            {{--| template |--}}
            @include('modules.pksanc.snippits.pokemon-cardlist-template')
        </x-datalist.cardlist.list>
    </div>
@endsection
