@extends('layouts.main')

@section('htmlTitle', 'Arsenal Armory')
@section('title', 'Armory')

@section('header')
    @vite([
        'resources/css/components/datalist/cardlist.css',
        'resources/css/modules/arsenal/arsenal.css',
        'resources/ts/modules/arsenal/armory.ts',
        'resources/css/modules/arsenal/armory.css',
    ])
@stop

@section('onload_functions', 'init()')

@section('content')
    {{--| Top bar |--}}
    <x-arsenal.topbar>
        <a href="{{route('arsenal.home.show')}}" class="interactive absolute right-0 top-0">Loadouts</a>
    </x-arsenal.topbar>

    {{--| Shared search bar |--}}
    <div class="flex justify-around mb-6">
        <div class="flex justify-center gap-3 mb-4 flex-wrap">
            <x-arsenal.item-type-filters/>
        </div>

        <div class="flex flex-row justify-center pt-2">
            <input id="armory-searchbar" type="text"
                   class="underlined text-center w-72 h-8"
                   placeholder="Search...">
            <button id="armory-search-confirm-button" class="interactive pl-2">Search</button>
        </div>

        <div class="flex justify-center gap-3 mb-4 flex-wrap">
            <x-arsenal.ownership-filters/>
        </div>
    </div>

    {{--| Armory card template |--}}
    <div class="hidden">
        @include('modules.arsenal.snippits.armory-cardlist-template')
    </div>

    {{--| Cardlist collective |--}}
    @foreach([
        'warframe',
        'primary',
        'secondary',
        'melee',
        'companion',
        'companion_weapon',
        'archgun',
        'archmelee',
    ] as $category)
        <div id="section-{{$category}}" class="arsenal-section">

            {{--| Hidden per-page selector |--}}
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
                <div id="{{$category}}-cardlist-content" class="hidden flex flex-wrap justify-center gap-6 pb-2"></div>
            </div>

            {{--| Pagination |--}}
            <x-datalist.parts.pagination
                id="{{$category}}-cardlist-pagination"
                url="{{route('data.arsenal.armory')}}/pages?category={{$category}}"
                pages_in_pagination="7"/>
        </div>
    @endforeach

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

                <x-arsenal.modal-row id="modal-name-row" label="Name" :hidden="true">
                    <input type="text" id="modal-name" class="underlined text-sm text-right"
                           placeholder="Custom name...">
                </x-arsenal.modal-row>

                <x-arsenal.modal-row id="modal-school-row" label="Focus School" :hidden="true">
                    <select id="modal-school" class="underlined text-sm">
                        <option value="">None</option>
                        <option value="madurai">Madurai</option>
                        <option value="vazarin">Vazarin</option>
                        <option value="naramon">Naramon</option>
                        <option value="unairu">Unairu</option>
                        <option value="zenurik">Zenurik</option>
                    </select>
                </x-arsenal.modal-row>

                <x-arsenal.modal-row label="Forma">
                    <input type="number" id="modal-forma" min="0" max="10" value="0"
                           class="underlined w-14 text-center text-sm">
                </x-arsenal.modal-row>

                <x-arsenal.modal-row id="modal-shards-row" label="Shards" :hidden="true">
                    <input type="number" id="modal-shards" min="0" max="5" value="0"
                           class="underlined w-14 text-center text-sm">
                </x-arsenal.modal-row>

                <x-arsenal.modal-row label="Orokin Reactor" label-id="modal-potato-label">
                    <input type="checkbox" id="modal-potato" class="w-4 h-4">
                </x-arsenal.modal-row>

                <x-arsenal.modal-row id="modal-exilus-row" label="Exilus Adapter" :hidden="true">
                    <input type="checkbox" id="modal-exilus" class="w-4 h-4">
                </x-arsenal.modal-row>

                <x-arsenal.modal-row id="modal-fashioned-row" label="Fashioned" :hidden="true">
                    <input type="checkbox" id="modal-fashioned" class="w-4 h-4">
                </x-arsenal.modal-row>

                <x-arsenal.modal-row label="Built">
                    <input type="checkbox" id="modal-built" class="w-4 h-4">
                </x-arsenal.modal-row>

                <x-arsenal.modal-row id="modal-riven-row" label="Riven" :hidden="true">
                    <input type="checkbox" id="modal-riven" class="w-4 h-4">
                </x-arsenal.modal-row>

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
@endsection
