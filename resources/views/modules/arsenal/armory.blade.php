@extends('layouts.main')

@section('htmlTitle', 'Arsenal Armory')
@section('title', 'Armory')

@section('header')
    @vite([
        'resources/css/components/datalist/cardlist.css',
        'resources/css/modules/arsenal/arsenal.css',
        'resources/ts/modules/arsenal/armory.ts',
    ])
@stop

@section('onload_functions', 'init()')

@section('content')
    {{--| Top bar |--}}
    <div class="flex justify-center">
        <div id="top-bar-container" class="relative">
            <a href="{{route('arsenal.home.show')}}" class="interactive absolute left-0 top-0">Loadouts</a>
            <a href="{{route('arsenal.foundry.show')}}" class="interactive absolute right-0 top-0">Foundry</a>
        </div>
    </div>

    <div class="mt-6 pb-4"></div>

    {{--| Shared template used by all section cardlists via data-template |--}}
    <div class="hidden">
        @include('modules.arsenal.snippits.armory-cardlist-template')
    </div>

    {{--| Shared search bar |--}}
    <div class="flex justify-center mb-6">
        <div class="flex flex-row justify-center pt-2">
            <input id="armory-searchbar" type="text"
                   class="underlined text-center w-72 h-8"
                   placeholder="Search...">
            <button id="armory-search-confirm-button" class="interactive pl-2">Search</button>
        </div>
    </div>

    {{--| Filter bar |--}}
    <div class="flex justify-center gap-3 mb-4 flex-wrap">

        {{-- Group 1: Variant --}}
        <div class="inline-flex bg-gray-100 border border-gray-200 rounded-lg p-0.5 gap-0.5 shadow-sm">
            <button class="armory-filter-btn w-8 h-8 flex items-center justify-center rounded bg-white text-red-900 shadow-sm transition-colors"
                    data-filter-group="variant" data-filter-value="all" title="All variants">
                <svg viewBox="0 0 16 16" width="14" height="14" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                    <path d="M5.5 8a2 2 0 1 0 0-4 2 2 0 0 0 0 4zm5 0a2 2 0 1 0 0-4 2 2 0 0 0 0 4z"/>
                </svg>
            </button>
            <button class="armory-filter-btn w-8 h-8 flex items-center justify-center rounded text-gray-400 hover:bg-gray-200 hover:text-gray-600 transition-colors"
                    data-filter-group="variant" data-filter-value="prime" title="Prime only">
                <svg viewBox="0 0 16 16" width="14" height="14" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                    <path d="M8 1.5 9.6 5.4H14l-3.4 2.6 1.3 3.9L8 9.5l-3.9 2.4 1.3-3.9L2 5.4h4.4z"/>
                </svg>
            </button>
            <button class="armory-filter-btn w-8 h-8 flex items-center justify-center rounded text-gray-400 hover:bg-gray-200 hover:text-gray-600 transition-colors"
                    data-filter-group="variant" data-filter-value="non-prime" title="Non-prime only">
                <svg viewBox="0 0 16 16" width="14" height="14" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="8" cy="8" r="5.5" fill="none" stroke="currentColor" stroke-width="1.5"/>
                </svg>
            </button>
        </div>

        {{-- Group 2: Item type --}}
        <div class="inline-flex bg-gray-100 border border-gray-200 rounded-lg p-0.5 gap-0.5 shadow-sm">
            <button class="armory-filter-btn w-8 h-8 flex items-center justify-center rounded bg-white text-red-900 shadow-sm transition-colors"
                    data-filter-group="itemType" data-filter-value="all" title="All types">
                <svg viewBox="0 0 16 16" width="14" height="14" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                    <rect x="2" y="2" width="5" height="5" rx="1"/><rect x="9" y="2" width="5" height="5" rx="1"/>
                    <rect x="2" y="9" width="5" height="5" rx="1"/><rect x="9" y="9" width="5" height="5" rx="1"/>
                </svg>
            </button>
            <button class="armory-filter-btn w-8 h-8 flex items-center justify-center rounded text-gray-400 hover:bg-gray-200 hover:text-gray-600 transition-colors"
                    data-filter-group="itemType" data-filter-value="warframe" title="Warframes only">
                <svg viewBox="0 0 16 16" width="14" height="14" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="8" cy="4" r="2.5"/>
                    <path d="M3.5 14a4.5 4.5 0 0 1 9 0z"/>
                </svg>
            </button>
            <button class="armory-filter-btn w-8 h-8 flex items-center justify-center rounded text-gray-400 hover:bg-gray-200 hover:text-gray-600 transition-colors"
                    data-filter-group="itemType" data-filter-value="primary" title="Primary weapons only">
                <svg viewBox="0 0 16 16" width="14" height="14" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                    <rect x="1" y="6" width="11" height="3" rx="1"/>
                    <rect x="12" y="6.5" width="3" height="2" rx=".5"/>
                    <rect x="1" y="9" width="3" height="3" rx=".5"/>
                </svg>
            </button>
            <button class="armory-filter-btn w-8 h-8 flex items-center justify-center rounded text-gray-400 hover:bg-gray-200 hover:text-gray-600 transition-colors"
                    data-filter-group="itemType" data-filter-value="secondary" title="Secondary weapons only">
                <svg viewBox="0 0 16 16" width="14" height="14" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                    <rect x="2" y="5" width="8" height="3" rx="1"/>
                    <rect x="10" y="5.5" width="3" height="2" rx=".5"/>
                    <rect x="3" y="8" width="2.5" height="3.5" rx=".5"/>
                </svg>
            </button>
            <button class="armory-filter-btn w-8 h-8 flex items-center justify-center rounded text-gray-400 hover:bg-gray-200 hover:text-gray-600 transition-colors"
                    data-filter-group="itemType" data-filter-value="melee" title="Melee weapons only">
                <svg viewBox="0 0 16 16" width="14" height="14" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                    <path d="M11.5 2 14 4.5l-8 8-1.5.5.5-1.5z"/>
                    <path d="M2 13.5 3.5 12l1.5 1.5L3.5 15z"/>
                </svg>
            </button>
            <button class="armory-filter-btn w-8 h-8 flex items-center justify-center rounded text-gray-400 hover:bg-gray-200 hover:text-gray-600 transition-colors"
                    data-filter-group="itemType" data-filter-value="companion" title="Companions only">
                <svg viewBox="0 0 16 16" width="14" height="14" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="5" cy="5.5" r="1.5"/>
                    <circle cx="8" cy="4" r="1.5"/>
                    <circle cx="11" cy="5.5" r="1.5"/>
                    <ellipse cx="8" cy="11" rx="3.5" ry="3"/>
                </svg>
            </button>
            <button class="armory-filter-btn w-8 h-8 flex items-center justify-center rounded text-gray-400 hover:bg-gray-200 hover:text-gray-600 transition-colors"
                    data-filter-group="itemType" data-filter-value="companion_weapon" title="Companion weapons only">
                <svg viewBox="0 0 16 16" width="14" height="14" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                    <rect x="3" y="6.5" width="7" height="2.5" rx=".8"/>
                    <rect x="10" y="7" width="2.5" height="1.5" rx=".5"/>
                    <rect x="4" y="9" width="1.5" height="2.5" rx=".5"/>
                    <circle cx="11.5" cy="10.5" r="1.5"/>
                </svg>
            </button>
            <button class="armory-filter-btn w-8 h-8 flex items-center justify-center rounded text-gray-400 hover:bg-gray-200 hover:text-gray-600 transition-colors"
                    data-filter-group="itemType" data-filter-value="archgun" title="Arch-Guns only">
                <svg viewBox="0 0 16 16" width="14" height="14" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                    <rect x="1" y="5" width="11" height="5" rx="1.5"/>
                    <rect x="12" y="6" width="3" height="3" rx=".5"/>
                    <rect x="1" y="10" width="5" height="4" rx=".5"/>
                </svg>
            </button>
            <button class="armory-filter-btn w-8 h-8 flex items-center justify-center rounded text-gray-400 hover:bg-gray-200 hover:text-gray-600 transition-colors"
                    data-filter-group="itemType" data-filter-value="archmelee" title="Arch-Melee only">
                <svg viewBox="0 0 16 16" width="14" height="14" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                    <rect x="7" y="3" width="2" height="11" rx="1"/>
                    <rect x="2.5" y="1.5" width="11" height="4" rx="1"/>
                </svg>
            </button>
        </div>

        {{-- Group 3: Ownership --}}
        <div class="inline-flex bg-gray-100 border border-gray-200 rounded-lg p-0.5 gap-0.5 shadow-sm">
            <button class="armory-filter-btn w-8 h-8 flex items-center justify-center rounded bg-white text-red-900 shadow-sm transition-colors"
                    data-filter-group="ownership" data-filter-value="all" title="All items">
                <svg viewBox="0 0 16 16" width="14" height="14" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                    <rect x="2" y="2" width="5" height="5" rx="1"/><rect x="9" y="2" width="5" height="5" rx="1"/>
                    <rect x="2" y="9" width="5" height="5" rx="1"/><rect x="9" y="9" width="5" height="5" rx="1"/>
                </svg>
            </button>
            <button class="armory-filter-btn w-8 h-8 flex items-center justify-center rounded text-gray-400 hover:bg-gray-200 hover:text-gray-600 transition-colors"
                    data-filter-group="ownership" data-filter-value="owned" title="Owned only">
                <svg viewBox="0 0 16 16" width="14" height="14" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="8" cy="8" r="5.5" fill="none" stroke="currentColor" stroke-width="1.5"/>
                    <path d="M5.5 8l2 2 3.5-3.5" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
            <button class="armory-filter-btn w-8 h-8 flex items-center justify-center rounded text-gray-400 hover:bg-gray-200 hover:text-gray-600 transition-colors"
                    data-filter-group="ownership" data-filter-value="unowned" title="Unowned only">
                <svg viewBox="0 0 16 16" width="14" height="14" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="8" cy="8" r="5.5" fill="none" stroke="currentColor" stroke-width="1.5"/>
                    <line x1="5" y1="8" x2="11" y2="8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
            </button>
        </div>

    </div>

    {{--| Category sections |--}}
    {{--| Ownership detail modal |--}}
    <x-modal id="armory-item-modal">
        <div class="w-full flex flex-col gap-3" style="min-width:22rem">

            {{--| Header: icon + name |--}}
            <div class="flex items-center gap-3 pb-1 border-b">
                <img id="modal-item-icon" class="w-12 h-12 object-contain flex-shrink-0" alt="">
                <span id="modal-item-name" class="font-semibold"></span>
            </div>

            {{--| Hidden state |--}}
            <input type="hidden" id="modal-user-uuid">
            <input type="hidden" id="modal-item-id">
            <input type="hidden" id="modal-item-type">
            <input type="hidden" id="modal-base-name">

            {{--| Unowned state |--}}
            <p id="modal-unowned-content" class="hidden text-gray-400 text-sm text-center py-2">
                Not in your collection
            </p>

            {{--| Owned form |--}}
            <div id="modal-form-content" class="hidden w-full flex flex-col gap-3">

                <div class="flex items-center justify-between gap-6">
                    <label class="text-sm text-gray-600">Forma</label>
                    <input type="number" id="modal-forma" min="0" max="10" value="0"
                           class="underlined w-14 text-center text-sm">
                </div>

                <div class="flex items-center justify-between gap-6">
                    <label id="modal-potato-label" class="text-sm text-gray-600">Orokin Reactor</label>
                    <input type="checkbox" id="modal-potato" class="w-4 h-4">
                </div>

                <div class="flex items-center justify-between gap-6">
                    <label class="text-sm text-gray-600">Built</label>
                    <input type="checkbox" id="modal-built" class="w-4 h-4">
                </div>

                <div id="modal-exilus-row" class="hidden flex items-center justify-between gap-6">
                    <label class="text-sm text-gray-600">Exilus Adapter</label>
                    <input type="checkbox" id="modal-exilus" class="w-4 h-4">
                </div>

                <div id="modal-fashioned-row" class="hidden flex items-center justify-between gap-6">
                    <label class="text-sm text-gray-600">Fashioned</label>
                    <input type="checkbox" id="modal-fashioned" class="w-4 h-4">
                </div>

                <div id="modal-riven-row" class="hidden flex items-center justify-between gap-6">
                    <label class="text-sm text-gray-600">Riven</label>
                    <input type="checkbox" id="modal-riven" class="w-4 h-4">
                </div>

                <div id="modal-school-row" class="hidden flex items-center justify-between gap-6">
                    <label class="text-sm text-gray-600">Focus School</label>
                    <select id="modal-school" class="underlined text-sm">
                        <option value="">None</option>
                        <option value="madurai">Madurai</option>
                        <option value="vazarin">Vazarin</option>
                        <option value="naramon">Naramon</option>
                        <option value="unairu">Unairu</option>
                        <option value="zenurik">Zenurik</option>
                    </select>
                </div>

                <div id="modal-name-row" class="hidden flex items-center justify-between gap-6">
                    <label class="text-sm text-gray-600">Name</label>
                    <input type="text" id="modal-name" class="underlined text-sm text-right"
                           placeholder="Custom name...">
                </div>

            </div>
        </div>

        <x-slot:buttons>
            <button id="modal-remove-btn" class="cancel_interactive text-sm hidden"
                    onclick="removeItem()">Remove</button>
            <button class="cancel_interactive text-sm"
                    onclick="closeModal('armory-item-modal')">Cancel</button>
            <button id="modal-duplicate-btn" class="interactive text-sm hidden"
                    onclick="duplicateItem()">Add Duplicate</button>
            <button id="modal-save-btn" class="interactive text-sm hidden"
                    onclick="saveItem()">Save</button>
            <button id="modal-add-btn" class="interactive text-sm hidden"
                    onclick="addItemFromModal()">Add to Collection</button>
        </x-slot:buttons>
    </x-modal>

    @foreach([
        ['warframe', 'Warframes'],
        ['primary', 'Primary Weapons'],
        ['secondary', 'Secondary Weapons'],
        ['melee', 'Melee Weapons'],
        ['companion', 'Companions'],
        ['companion_weapon', 'Companion Weapons'],
        ['archgun', 'Arch-Guns'],
        ['archmelee', 'Arch-Melee'],
    ] as [$category, $label])
    <div id="section-{{$category}}" class="flex flex-col gap-2 justify-center max-w-screen-2xl mx-auto w-full mb-8">

        {{--| Hidden per-page selector required by pagination |--}}
        <div class="hidden">
            @component('components.datalist.parts.perpage_select')
                @slot('id', $category . '-cardlist-pagination-perpage-selector')
                @slot('selected_option', 9)
                @slot('options', [3, 6, 9, 12, 18, 30])
            @endcomponent
        </div>

        {{--| Datalist |--}}
        <div id="{{$category}}-cardlist"
             class="dataprovider cardlist text-center w-full mt-2"
             data-content-url="{{route('data.arsenal.armory')}}?category={{$category}}"
             data-template="armory-cardlist-template"
             data-history="false"
             data-empty-body="<p class='text-center w-full'>No results</p>">
            <x-datalist.parts.load-spinner dataprovider_id="{{$category}}-cardlist"/>
            <div id="{{$category}}-cardlist-content" class="hidden flex flex-nowrap gap-6 overflow-x-auto pb-2"></div>
        </div>

        {{--| Pagination |--}}
        <x-datalist.parts.pagination
            id="{{$category}}-cardlist-pagination"
            url="{{route('data.arsenal.armory')}}/pages?category={{$category}}"
            pages_in_pagination="7"/>
    </div>
    @endforeach
@endsection
