@extends('layouts.main')

@section('htmlTitle', 'Arsenal Foundry')
@section('title', 'Foundry')

@section('header')
    @vite([
        'resources/css/components/datalist/cardlist.css',
        'resources/css/modules/arsenal/arsenal.css',
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
