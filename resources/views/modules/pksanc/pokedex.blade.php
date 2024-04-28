@extends('layouts.main')

@section('htmlTitle', 'PKSanc Pokedex')
@section('title', 'Pokedex')

@section('header')
    @vite([
        'resources/css/components/datalist/cardlist.css',
        'resources/css/project/pksanc/box.css',
        'resources/ts/modules/pksanc/pokedex.ts'
    ])
@stop

@section('onload_functions', 'init()')

@section('content')
    {{--| title |--}}
    <div class="flex justify-center">
        <div id="top-bar-container" class="relative">
            <a href="{{route('pksanc.home.show')}}" class="interactive">Box</a>
            <a href="{{route('pksanc.deposit.show')}}" class="interactive absolute right-0 top-0">Deposit Pokemon</a>
        </div>
    </div>

    <div class="mt-6 pb-4"></div>

    {{--| cardlist |--}}
    <div class="flex flex-row justify-center mb-3" dusk="form">
        <x-datalist.cardlist.list id="pokedex-cardlist" url="{{route('pksanc.pokedex.cardlist')}}" perpage="12" :perpageoptions="$perpageoptions">
            {{--| template |--}}
            <x-datalist.cardlist.template id="pokedex-cardlist">
                <div class="flex justify-center">
                    <div class="flex flex-col justify-center items-center h-full mr-1 w-8 gap-2">
                        <div class="flex justify-center items-center gap-1 h-6">
                            <img src="{{asset('img/modules/pksanc/pokeball/poke-ball.png')}}" alt="amount caught" title="amount caught" class="w-8 h-8">
                            <span class="text-center" data-name="amount_owned"></span>
                        </div>
                        <div class="flex justify-center items-center gap-1 h-6">
                            <span class="flex justify-center items-center w-8 h-8">
                                <img src="{{asset('img/modules/pksanc/icon/shiny.png')}}" alt="shinies caught" title="shinies caught" class="w-6 h-6">
                            </span>
                            <span class="text-center" data-name="shinies_owned"></span>
                        </div>
                    </div>

                    <div class="flex items-center px-3 h-full">
                        <div class="divider"></div>
                    </div>

                    <div class="flex flex-col justify-center gap-0 w-24">
                        <img data-name="sprite" data-alt-name="species" title="sprite" class="w-24 h-24"
                             data-add-class-is-true-name="unowned" data-class-to-add="grayscale">
                        <div class="flex flex-col items-center">
                            <span class="text-center" data-name="species_name" title="name"></span>
                            <span class="text-center" data-name="form_name" title="name"></span>
                        </div>
                    </div>
                </div>
            </x-datalist.cardlist.template>
        </x-datalist.cardlist.list>
    </div>
@endsection
