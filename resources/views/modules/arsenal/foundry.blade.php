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
            <a href="{{route('arsenal.armory.show')}}" class="interactive absolute right-0 top-0">Armory</a>
        </div>
    </div>

    <div class="mt-6 pb-4"></div>

    {{--| Search bar |--}}
    <div class="flex justify-center mb-6">
        <div id="foundry-cardlist-searchbar" class="searchbar flex flex-row justify-center pt-2"
             data-input-ID="foundry-searchbar"
             data-confirm-button-ID="foundry-search-button">
            <input id="foundry-searchbar" type="text"
                   class="underlined text-center w-72 h-8"
                   placeholder="Search blueprints...">
            <button id="foundry-search-button" class="interactive pl-2">Search</button>
        </div>
    </div>

    {{--| Filter bar |--}}
    <div class="flex justify-center gap-3 mb-4">

        {{-- Group 1: Variant --}}
        <div class="inline-flex bg-gray-100 border border-gray-200 rounded-lg p-0.5 gap-0.5 shadow-sm">
            <button class="foundry-filter-btn w-8 h-8 flex items-center justify-center rounded bg-white text-red-900 shadow-sm transition-colors"
                    data-filter-group="variant" data-filter-value="all" title="All variants">
                <svg viewBox="0 0 16 16" width="14" height="14" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                    <path d="M5.5 8a2 2 0 1 0 0-4 2 2 0 0 0 0 4zm5 0a2 2 0 1 0 0-4 2 2 0 0 0 0 4z"/>
                </svg>
            </button>
            <button class="foundry-filter-btn w-8 h-8 flex items-center justify-center rounded text-gray-400 hover:bg-gray-200 hover:text-gray-600 transition-colors"
                    data-filter-group="variant" data-filter-value="prime" title="Prime only">
                <svg viewBox="0 0 16 16" width="14" height="14" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                    <path d="M8 1.5 9.6 5.4H14l-3.4 2.6 1.3 3.9L8 9.5l-3.9 2.4 1.3-3.9L2 5.4h4.4z"/>
                </svg>
            </button>
            <button class="foundry-filter-btn w-8 h-8 flex items-center justify-center rounded text-gray-400 hover:bg-gray-200 hover:text-gray-600 transition-colors"
                    data-filter-group="variant" data-filter-value="non-prime" title="Non-prime only">
                <svg viewBox="0 0 16 16" width="14" height="14" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="8" cy="8" r="5.5" fill="none" stroke="currentColor" stroke-width="1.5"/>
                </svg>
            </button>
        </div>

        {{-- Group 2: Item type --}}
        <div class="inline-flex bg-gray-100 border border-gray-200 rounded-lg p-0.5 gap-0.5 shadow-sm">
            <button class="foundry-filter-btn w-8 h-8 flex items-center justify-center rounded bg-white text-red-900 shadow-sm transition-colors"
                    data-filter-group="itemType" data-filter-value="all" title="All types">
                <svg viewBox="0 0 16 16" width="14" height="14" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                    <rect x="2" y="2" width="5" height="5" rx="1"/><rect x="9" y="2" width="5" height="5" rx="1"/>
                    <rect x="2" y="9" width="5" height="5" rx="1"/><rect x="9" y="9" width="5" height="5" rx="1"/>
                </svg>
            </button>
            <button class="foundry-filter-btn w-8 h-8 flex items-center justify-center rounded text-gray-400 hover:bg-gray-200 hover:text-gray-600 transition-colors"
                    data-filter-group="itemType" data-filter-value="warframe" title="Warframes only">
                <svg viewBox="0 0 16 16" width="14" height="14" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="8" cy="4" r="2.5"/>
                    <path d="M3.5 14a4.5 4.5 0 0 1 9 0z"/>
                </svg>
            </button>
            <button class="foundry-filter-btn w-8 h-8 flex items-center justify-center rounded text-gray-400 hover:bg-gray-200 hover:text-gray-600 transition-colors"
                    data-filter-group="itemType" data-filter-value="primary" title="Primary weapons only">
                <svg viewBox="0 0 16 16" width="14" height="14" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                    <rect x="1" y="6" width="11" height="3" rx="1"/>
                    <rect x="12" y="6.5" width="3" height="2" rx=".5"/>
                    <rect x="1" y="9" width="3" height="3" rx=".5"/>
                </svg>
            </button>
            <button class="foundry-filter-btn w-8 h-8 flex items-center justify-center rounded text-gray-400 hover:bg-gray-200 hover:text-gray-600 transition-colors"
                    data-filter-group="itemType" data-filter-value="secondary" title="Secondary weapons only">
                <svg viewBox="0 0 16 16" width="14" height="14" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                    <rect x="2" y="5" width="8" height="3" rx="1"/>
                    <rect x="10" y="5.5" width="3" height="2" rx=".5"/>
                    <rect x="3" y="8" width="2.5" height="3.5" rx=".5"/>
                </svg>
            </button>
            <button class="foundry-filter-btn w-8 h-8 flex items-center justify-center rounded text-gray-400 hover:bg-gray-200 hover:text-gray-600 transition-colors"
                    data-filter-group="itemType" data-filter-value="melee" title="Melee weapons only">
                <svg viewBox="0 0 16 16" width="14" height="14" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                    <path d="M11.5 2 14 4.5l-8 8-1.5.5.5-1.5z"/>
                    <path d="M2 13.5 3.5 12l1.5 1.5L3.5 15z"/>
                </svg>
            </button>
            <button class="foundry-filter-btn w-8 h-8 flex items-center justify-center rounded text-gray-400 hover:bg-gray-200 hover:text-gray-600 transition-colors"
                    data-filter-group="itemType" data-filter-value="companion" title="Companions only">
                <svg viewBox="0 0 16 16" width="14" height="14" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="5" cy="5.5" r="1.5"/>
                    <circle cx="8" cy="4" r="1.5"/>
                    <circle cx="11" cy="5.5" r="1.5"/>
                    <ellipse cx="8" cy="11" rx="3.5" ry="3"/>
                </svg>
            </button>
        </div>

        {{-- Group 3: Ownership --}}
        <div class="inline-flex bg-gray-100 border border-gray-200 rounded-lg p-0.5 gap-0.5 shadow-sm">
            <button class="foundry-filter-btn w-8 h-8 flex items-center justify-center rounded bg-white text-red-900 shadow-sm transition-colors"
                    data-filter-group="ownership" data-filter-value="all" title="All items">
                <svg viewBox="0 0 16 16" width="14" height="14" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                    <rect x="2" y="2" width="5" height="5" rx="1"/><rect x="9" y="2" width="5" height="5" rx="1"/>
                    <rect x="2" y="9" width="5" height="5" rx="1"/><rect x="9" y="9" width="5" height="5" rx="1"/>
                </svg>
            </button>
            <button class="foundry-filter-btn w-8 h-8 flex items-center justify-center rounded text-gray-400 hover:bg-gray-200 hover:text-gray-600 transition-colors"
                    data-filter-group="ownership" data-filter-value="unowned" title="Incomplete only">
                <svg viewBox="0 0 16 16" width="14" height="14" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="8" cy="8" r="5.5" fill="none" stroke="currentColor" stroke-width="1.5"/>
                    <line x1="5" y1="8" x2="11" y2="8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
            </button>
            <button class="foundry-filter-btn w-8 h-8 flex items-center justify-center rounded text-gray-400 hover:bg-gray-200 hover:text-gray-600 transition-colors"
                    data-filter-group="ownership" data-filter-value="owned" title="Complete only">
                <svg viewBox="0 0 16 16" width="14" height="14" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="8" cy="8" r="5.5" fill="none" stroke="currentColor" stroke-width="1.5"/>
                    <path d="M5.5 8l2 2 3.5-3.5" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
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

        {{--| Hidden per-page selector required by pagination |--}}
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
