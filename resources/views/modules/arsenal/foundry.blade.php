@extends('layouts.main')

@section('htmlTitle', 'Arsenal Foundry')
@section('title', 'Foundry')

@section('header')
    @vite([
        'resources/css/components/datalist/cardlist.css',
        'resources/css/modules/arsenal/arsenal.css',
        'resources/ts/components/modal.ts',
        'resources/ts/modules/arsenal/foundry.ts',
    ])
@stop

@section('onload_functions', 'init()')

@section('content')
    {{--| Top bar |--}}
    <div class="flex justify-center">
        <div id="top-bar-container" class="relative">
            <a href="{{route('arsenal.home.show')}}" class="interactive absolute left-0 top-0">Loadouts</a>
        </div>
    </div>

    <div class="mt-6 pb-4"></div>

    {{--| Search bar |--}}
    <div class="flex justify-around mb-6">
        <div class="flex justify-center gap-3 mb-4 flex-wrap">
            <x-datalist.filters.filter-group>
                <x-datalist.filters.filter-item title="All" filter-group="itemType" value="all" :selected="true">{{asset('img/modules/arsenal/icon/all.png')}}</x-datalist.filters.filter-item>
                <x-datalist.filters.filter-item title="Warframe" filter-group="itemType" value="warframe">{{asset('img/modules/arsenal/icon/warframe.png')}}</x-datalist.filters.filter-item>
                <x-datalist.filters.filter-item title="Primary" filter-group="itemType" value="primary">{{asset('img/modules/arsenal/icon/primary_rifle.png')}}</x-datalist.filters.filter-item>
                <x-datalist.filters.filter-item title="Secondary" filter-group="itemType" value="secondary">{{asset('img/modules/arsenal/icon/secondary.png')}}</x-datalist.filters.filter-item>
                <x-datalist.filters.filter-item title="Melee" filter-group="itemType" value="melee">{{asset('img/modules/arsenal/icon/melee.png')}}</x-datalist.filters.filter-item>
                <x-datalist.filters.filter-item title="Companion" filter-group="itemType" value="companion">{{asset('img/modules/arsenal/icon/companion.png')}}</x-datalist.filters.filter-item>
                <x-datalist.filters.filter-item title="Archwing" filter-group="itemType" value="archwing">{{asset('img/modules/arsenal/icon/archwing.png')}}</x-datalist.filters.filter-item>
            </x-datalist.filters.filter-group>
        </div>

        <div id="foundry-cardlist-searchbar" class="searchbar flex flex-row justify-center pt-2"
             data-input-ID="foundry-searchbar"
             data-confirm-button-ID="foundry-search-button">
            <input id="foundry-searchbar" type="text"
                   class="underlined text-center w-72 h-8"
                   placeholder="Search blueprints...">
            <button id="foundry-search-button" class="interactive pl-2">Search</button>
        </div>

        <div class="flex justify-center gap-3 mb-4 flex-wrap">
            <x-datalist.filters.filter-group>
                <x-datalist.filters.filter-item title="All" filter-group="variant" value="all" :selected="true">{{asset('img/modules/arsenal/icon/all.png')}}</x-datalist.filters.filter-item>
                <x-datalist.filters.filter-item title="Prime" filter-group="variant" value="prime">{{asset('img/modules/arsenal/icon/prime.png')}}</x-datalist.filters.filter-item>
                <x-datalist.filters.filter-item title="Mundane" filter-group="variant" value="non-prime">{{asset('img/modules/arsenal/icon/mundane.png')}}</x-datalist.filters.filter-item>
            </x-datalist.filters.filter-group>

            <x-datalist.filters.filter-group>
                <x-datalist.filters.filter-item title="All" filter-group="ownership" value="all" :selected="true">{{asset('img/modules/arsenal/icon/all.png')}}</x-datalist.filters.filter-item>
                <x-datalist.filters.filter-item title="Owned" filter-group="ownership" value="owned">{{asset('img/modules/arsenal/icon/owned.png')}}</x-datalist.filters.filter-item>
                <x-datalist.filters.filter-item title="Unowned" filter-group="ownership" value="unowned">{{asset('img/modules/arsenal/icon/unowned.png')}}</x-datalist.filters.filter-item>
            </x-datalist.filters.filter-group>
        </div>
    </div>

    {{--| Craft confirmation modal |--}}
    <x-modal.modal id="craft-modal" confirm_method="confirmCraft()" confirm_text="Craft">
        <div class="text-center">
            <svg class="mx-auto mb-3 w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
            </svg>
            <h3 class="mb-2 text-lg font-semibold text-gray-700">Craft Blueprint</h3>
            <p class="text-gray-500 text-sm">Craft <strong id="craft-modal-name"></strong>?</p>
            <p class="text-gray-400 text-xs mt-1">All components will be consumed and the item added to your armory.</p>
        </div>
    </x-modal.modal>

    {{--| Hidden card template |--}}
    <div class="hidden">
        @include('modules.arsenal.snippits.foundry-cardlist-template')
    </div>

    {{--| Blueprint cardlist |--}}
    <div class="flex flex-col gap-2 justify-center max-w-screen-2xl mx-auto w-full mb-8">

        {{--| Hidden per-page selector |--}}
        <div class="hidden">
            @component('components.datalist.parts.perpage_select')
                @slot('id', 'foundry-cardlist-pagination-perpage-selector')
                @slot('selected_option', 20)
                @slot('options', [10, 20, 40])
            @endcomponent
        </div>

        <div id="foundry-cardlist"
             class="dataprovider cardlist text-center w-full"
             data-content-url="{{route('data.arsenal.foundry')}}"
             data-template="foundry-cardlist-template"
             data-history="false"
             data-empty-body="<p class='text-center w-full py-4 text-gray-400'>No blueprints found</p>">
            <x-datalist.parts.load-spinner dataprovider_id="foundry-cardlist"/>
            <div id="foundry-cardlist-content" class="hidden flex flex-wrap gap-4 justify-center"></div>
        </div>

        <x-datalist.parts.pagination
            id="foundry-cardlist-pagination"
            url="{{route('data.arsenal.foundry')}}/pages"
            pages_in_pagination="7"/>
    </div>
@endsection
