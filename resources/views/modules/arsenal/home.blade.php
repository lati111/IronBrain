@extends('layouts.main')

@section('htmlTitle', 'Arsenal')
@section('title', 'Arsenal')

@section('header')
    @vite([
        'resources/css/components/datalist/cardlist.css',
        'resources/css/modules/arsenal/arsenal.css',
        'resources/ts/modules/arsenal/home.ts',
    ])
@stop

@section('onload_functions', 'init()')

@section('content')
    {{--| Top bar |--}}
    <div class="flex justify-center">
        <div id="top-bar-container" class="relative">
            <a href="{{route('arsenal.foundry.show')}}" class="interactive absolute left-0 top-0">Foundry</a>
            <a href="{{route('arsenal.armory.show')}}" class="interactive absolute right-0 top-0">Armory</a>
        </div>
    </div>

    <div class="mt-6 pb-4"></div>

    {{--| New Loadout button |--}}
    <div class="flex justify-center mb-4">
        <button class="interactive" onclick="createLoadout()">+ New Loadout</button>
    </div>

    {{--| Slot picker modal |--}}
    <x-modal id="slot-picker-modal">
        <div class="flex flex-col gap-3" style="min-width: 28rem; max-width: 36rem">

            {{--| Header |--}}
            <div class="flex items-center pb-1 border-b">
                <span id="slot-picker-slot-label" class="font-semibold"></span>
            </div>

            {{--| Search |--}}
            <div class="flex gap-2">
                <input id="slot-picker-searchbar" type="text"
                       class="underlined text-center flex-1 h-8" placeholder="Search...">
                <button id="slot-picker-search-button" class="interactive text-sm px-3">Search</button>
            </div>

            {{--| Hidden item template |--}}
            <div class="hidden">
                @include('modules.arsenal.snippits.slot-picker-item-template')
            </div>

            {{--| Items cardlist |--}}
            <div id="slot-picker-cardlist"
                 class="dataprovider cardlist text-center w-full"
                 data-content-url="{{route('data.arsenal.owned-slot')}}"
                 data-template="slot-picker-item-template"
                 data-history="false"
                 data-empty-body="<p class='text-center w-full py-4 text-gray-400'>No owned items for this slot</p>">
                <x-datalist.parts.load-spinner dataprovider_id="slot-picker-cardlist"/>
                <div id="slot-picker-cardlist-content" class="hidden flex flex-wrap gap-3 max-h-80 overflow-y-auto"></div>
            </div>

        </div>

        <x-slot:buttons>
            <button id="slot-picker-clear-btn" class="cancel_interactive text-sm hidden"
                    onclick="clearSlot()">Clear Slot</button>
            <button class="cancel_interactive text-sm"
                    onclick="closeModal('slot-picker-modal')">Cancel</button>
        </x-slot:buttons>
    </x-modal>

    {{--| Loadout cardlist |--}}
    <div class="flex flex-row justify-center mb-3">
        <x-datalist.cardlist.list id="loadout-cardlist" url="{{route('data.arsenal.loadouts')}}">
            @include('modules.arsenal.snippits.loadout-cardlist-template')
        </x-datalist.cardlist.list>
    </div>
@endsection
