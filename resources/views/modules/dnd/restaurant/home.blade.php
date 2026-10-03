@extends('layouts.main')

@section('htmlTitle', 'DnD Restaurant Simulator')
@section('title', 'DnD Restaurant Simulator')

@section('header')
    @vite([
        'resources/css/components/datalist/cardlist.css',
        'resources/ts/modules/dnd/restaurant/home.ts',
    ])
@stop

@section('onload_functions', 'init()')

@section('content')
    <section class="pt-3 pb-5 text-center container">
        <h1 class="title">DnD Restaurant Simulator</h1>
    </section>

    {{--| Cardlist topbar |--}}
    <div class="flex justify-around items-center mb-4">
        <span class="w-64"></span>

        <x-datalist.parts.searchbar id="restaurant-cardlist"></x-datalist.parts.searchbar>

        <button class="interactive w-64" onclick="openCreateRestaurantModal()" dusk="restaurant-create">+ New Restaurant</button>
    </div>

    {{--| Restaurant cardlist |--}}
    <div class="flex flex-row justify-center mb-3">
        <x-datalist.cardlist.list id="restaurant-cardlist" url="{{route('data.dnd.restaurant.overview')}}" :include-searchbar="false">
            @include('modules.dnd.restaurant.snippits.restaurant-cardlist-template')
        </x-datalist.cardlist.list>
    </div>

    {{--| Create restaurant modal |--}}
    <x-modal id="restaurant-create-modal">
        <h3 class="title py-1">New Restaurant</h3>

        <x-form.input-wrapper label_text="Name" cls="w-full">
            <input type="text" id="restaurant-create-name" name="name" class="underlined" maxlength="64"
                   placeholder="The Prancing Pony" onkeydown="if (event.key === 'Enter') createRestaurant()" required/>
        </x-form.input-wrapper>

        <x-slot:buttons>
            <button class="cancel_interactive px-5 py-2.5"
                    onclick="closeModal('restaurant-create-modal')">Cancel</button>
            <button class="interactive px-5 py-2.5" dusk="confirm"
                    onclick="createRestaurant()">Create</button>
        </x-slot:buttons>
    </x-modal>
@endsection
