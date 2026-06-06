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
            <a href="{{route('arsenal.home.show')}}" class="interactive">Loadouts</a>
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

    {{--| Category sections |--}}
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
    <div class="flex flex-col gap-2 justify-center max-w-screen-2xl mx-auto w-full mb-8">
        <h4 class="text-center">{{$label}}</h4>

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
