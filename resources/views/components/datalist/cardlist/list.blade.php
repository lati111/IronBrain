@props([
    'id',
    'url',
    'history',
    'dynamic',
    'filtering',
    'per_page',
    'per_page_options',
    'pages_in_pagination',
])

{{--| Container |--}}
<div id="{{$id}}-disable-container" class="flex flex-col gap-2 justify-center max-w-screen-2xl w-full">
    {{--| Filter list modal |--}}
    @if(filter_var($filtering ?? false, FILTER_VALIDATE_BOOLEAN))
        <x-datalist.parts.filterlist-modal id="{{$id}}"></x-datalist.parts.filterlist-modal>
    @endif

    {{--| Per page selector |--}}
    <div class="hidden">
        @component('components.datalist.parts.perpage_select')
            @slot('id', $id.'-pagination-perpage-selector')
            @slot('selected_option', intval($per_page ?? 9))
            @slot('options', $per_page_options ?? [3,6,9,12,18,30])
        @endcomponent
    </div>

    {{--| Searchbar |--}}
    <div class="flex justify-center">
        <x-datalist.parts.searchbar id="{{$id}}"></x-datalist.parts.searchbar>
    </div>

    {{--| Filter list display |--}}
    @if(filter_var($filtering ?? false, FILTER_VALIDATE_BOOLEAN))
        <x-datalist.parts.filterlist id="{{$id}}" url="{{$url}}/filters"></x-datalist.parts.filterlist>
    @endif

    {{--| Template |--}}
    <div class="template hidden">{{$slot}}</div>

    {{--| Main datalist |--}}
    <div
        id="{{$id}}"
        class="dataprovider cardlist text-center w-full mt-6"
        data-content-url="{{$url}}"
        data-empty-body="<p><class='text-center w-full'>No results</p>"
        @if(filter_var($history ?? true, FILTER_VALIDATE_BOOLEAN) === false)data-history="false" @endif
        @if(filter_var($dynamic ?? false, FILTER_VALIDATE_BOOLEAN))data-dynamic-url="true" @endif
        @if(filter_var($filtering ?? false, FILTER_VALIDATE_BOOLEAN))
            data-option-content-cls="block p-2 border-transparent border-l-4 group-hover:border-red-600 group-hover:bg-gray-100 h-9"
            data-filter-item-container-cls="underlined flex gap-2 justify-center px-2"
            data-filter-delete-button-content="<img src='{{asset('img/icons/x.svg')}}' alt='delete' class='interactive w-5 h-5'>"
        @endif
    >
        {{--| Load spinner |--}}
        <x-datalist.parts.load-spinner dataprovider_id="{{$id}}"/>

        {{--| Datalist body |--}}
        <div id="{{$id}}-content" class="hidden flex justify-center flex-wrap gap-6"></div>
    </div>

    {{--| Pagination |--}}
    <x-datalist.parts.pagination id="{{$id}}-pagination" url="{{$url}}/pages" pages_in_pagination="{{$pages_in_pagination ?? 7}}"></x-datalist.parts.pagination>
</div>
