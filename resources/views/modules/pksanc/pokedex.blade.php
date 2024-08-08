@extends('layouts.main')

@section('htmlTitle', 'PKSanc Pokedex')
@section('title', 'Pokedex')

@section('header')
    @vite([
        'resources/css/components/datalist/cardlist.css',
        'resources/css/modules/pksanc/box.css',
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

    <div class="pb-4"></div>

    <div class="flex justify-end w-[93%] max-w-screen-2xl">
        <x-datalist.elements.filter-checkbox dataprovider_id="pokedex-cardlist" name="hidden" data-unchecked-operator="=" data-unchecked-value="0">
            Show hidden
        </x-datalist.elements.filter-checkbox>
    </div>

    {{--| cardlist |--}}
    <div class="flex flex-row justify-center mb-3" dusk="form">
        <x-datalist.cardlist.list id="pokedex-cardlist" url="{{route('data.pksanc.pokedex')}}" per_page="12" :per_page_options="$perpageoptions" filtering="true">
            {{--| template |--}}
            <x-datalist.cardlist.template dataprovider_id="pokedex-cardlist">
                <input type="hidden" name="pokedex_id">
                <input type="hidden" name="form_index">
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

                    <div class="flex items-center px-3 h-full">
                        <div class="divider"></div>
                    </div>

                    <div class="flex flex-col justify-center items-center h-full mr-1 w-8 gap-2">
                        <div class="flex justify-center items-center w-6 h-6">
                            <div data-hide-if-true-name="owned">
                                <x-form.input.checkbox name="marked-as-read" onchange="markAsRead(this.closest('.card'), this.checked)"/>
                            </div>

                            <div data-hide-if-true-name="unowned">
                                <x-form.input.checkbox name="" checked disabled/>
                            </div>
                        </div>

                        <div class="flex justify-center items-center gap-1 h-6 w-6 mt-1s">
                            <button id="visible_button" onclick="markAsHidden(this.closest('.card'), true)" data-hide-if-true-name="marked-as-hidden">
                                <img src="{{asset('img/icons/visible.svg')}}" class="interactive">
                            </button>

                            <button id="hidden_button" class="hidden" onclick="markAsHidden(this.closest('.card'), false)" data-show-if-true-name="marked-as-hidden">
                                <img src="{{asset('img/icons/invisible.svg')}}" class="interactive">
                            </button>
                        </div>
                    </div>
                </div>
            </x-datalist.cardlist.template>
        </x-datalist.cardlist.list>
    </div>
@endsection
