@extends('layouts.main')

@section('htmlTitle', 'Arsenal')
@section('title', 'Arsenal')

@section('header')
    @vite([
        'resources/css/components/datalist/cardlist.css',
        'resources/css/modules/arsenal/arsenal.css',
        'resources/css/modules/arsenal/loadouts.css',
        'resources/ts/modules/arsenal/home.ts',
    ])
@stop

@section('onload_functions', 'init()')

@section('content')
    {{--| Top bar |--}}
    <x-arsenal.topbar>
        <a href="{{route('arsenal.armory.show')}}" class="interactive absolute left-0 top-0">Armory</a>
        <a href="{{route('arsenal.foundry.show')}}" class="interactive absolute right-0 top-0">Foundry</a>
    </x-arsenal.topbar>

    {{--| Cardlist topbar |--}}
    <div class="flex justify-around items-center mb-4">
        <span class="w-64"></span>

        <x-datalist.parts.searchbar id="loadout-cardlist"></x-datalist.parts.searchbar>

        <button class="interactive w-64" onclick="createLoadout()">+ New Loadout</button>
    </div>

    {{--| Loadout cardlist |--}}
    <div class="flex flex-row justify-center mb-3">
        <x-datalist.cardlist.list id="loadout-cardlist" url="{{route('data.arsenal.loadouts')}}" :include-searchbar="false">
            @include('modules.arsenal.snippits.loadout-cardlist-template')
        </x-datalist.cardlist.list>
    </div>

    {{--| Slot picker modal |--}}
    <x-modal id="slot-picker-modal">
        <div class="flex flex-col gap-3" style="min-width: 28rem; max-width: 36rem">

            {{--| Header |--}}
            <div class="flex items-center pb-1 border-b">
                <span id="slot-picker-slot-label" class="font-semibold"></span>
            </div>

            {{--| Search |--}}
            <x-datalist.parts.searchbar id="slot-picker-cardlist"></x-datalist.parts.searchbar>

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
@endsection
