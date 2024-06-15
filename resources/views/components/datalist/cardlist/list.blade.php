{{--| Container |--}}
<div id="{{$id}}-disable-container" class="flex flex-col gap-2 justify-center max-w-screen-2xl w-full">
    {{--| Filter list modal |--}}
    <x-datalist.parts.filterlist-modal id="{{$id}}"></x-datalist.parts.filterlist-modal>

    {{--| Per page selector |--}}
    <div class="hidden">
        {{$buttons_center_right ?? ''}}
        @component('components.datalist.parts.perpage_select')
            @slot('id', $id.'-pagination-perpage-selector')
            @slot('selected_option', intval($perpage ?? 9))
            @slot('options', $perpageoptions ?? [3,6,9,12,18,30])
        @endcomponent
        {{$buttons_right ?? ''}}
    </div>

    {{--| Searchbar |--}}
    <div class="flex justify-center">
        <div class="">
            {{$buttons_left ?? ''}}
            <x-datalist.parts.searchbar id="{{$id}}"></x-datalist.parts.searchbar>
            {{$buttons_center_left ?? ''}}
        </div>
    </div>

    {{--| Filter list |--}}
    @if(($filtering ?? false) === true || ($filtering ?? 'false') === 'true')
        <x-datalist.parts.filterlist id="{{$id}}" url="{{$url}}/filters"></x-datalist.parts.filterlist>
    @endif

    {{--| Template |--}}
    <div class="template hidden">{{$slot}}</div>

    {{--| Main datalist |--}}
    <div
        id="{{$id}}"
        data-content-url="{{$url}}"
        data-pagination-ID="{{$id}}-pagination"
        data-searchbar-ID="{{$id}}-searchbar"
        data-empty-body="<p><class='text-center w-full'>No results</p>"
        @if(($history ?? true) === false || ($history ?? 'true') === 'false')data-history="false" @endif
        @if(($dynamic ?? false) === true || ($dynamic ?? 'false') === 'true')data-dynamic-url="true" @endif
        @isset($activity_column)data-activity-key="{{$activity_column}}" @endisset
        data-option-content-cls="block p-2 border-transparent border-l-4 group-hover:border-red-600 group-hover:bg-gray-100 h-9"
        data-filter-item-container-cls="underlined flex gap-2 justify-center px-2"
        data-filter-delete-button-content="<img src='{{asset('img/icons/x.svg')}}' alt='delete' class='interactive w-5 h-5'>"
        class="dataprovider cardlist text-center w-full mt-6"
    >
        {{--| Load spinner |--}}
        <div id="{{$id}}-spinner" class="spinner flex justify-center py-6">
            <img src="{{asset('img/icons/loading.svg')}}" class="animate-spin" alt="loading">
        </div>

        {{--| Datalist body |--}}
        <div id="{{$id}}-content" class="hidden flex justify-center flex-wrap gap-6"></div>
    </div>

    {{--| Pagination |--}}
    <x-datalist.parts.pagination id="{{$id}}-pagination" url="{{$url}}/pages" pages_in_pagination="{{$pagesInPagination ?? 7}}"></x-datalist.parts.pagination>
</div>
