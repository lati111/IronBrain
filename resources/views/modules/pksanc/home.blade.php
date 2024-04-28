@extends('layouts.master')

@section('htmlTitle', 'PKSanc Box')
@section('title', 'Box')

@section('css')
    @vite([
        'resources/css/components/datalist/cardlist.css',
        'resources/css/project/pksanc/box.css'
    ])
@stop

@section('onloadFunction', 'init()')

@section('content')
    <div class="flex justify-center">
        <div>
            {{--| title |--}}
            <div id="flex justify-center">
                <div id="top-bar-container" class="relative">
                    <a href="{{route('pksanc.deposit.show')}}" class="interactive absolute right-0 top-0">Deposit Pokemon</a>
                </div>
            </div>

            <div class="mt-6 pb-4"></div>

            {{--| cardlist |--}}
            <div class="flex flex-row justify-center mb-3" dusk="form">
                <x-datalist.cardlist.list id="pokemon-cardlist" url="{{route('pksanc.overview.cardlist')}}">
                    {{--| template |--}}
                    @include('modules.pksanc.snippits.pokemon-cardlist-template')
                </x-datalist.cardlist.list>
            </div>
        </div>
    </div>

@endsection

@section('script')
    @vite(['resources/ts/modules/pksanc/box.ts'])
@stop
