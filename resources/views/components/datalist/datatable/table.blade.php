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

    {{--| Table |--}}
    <div class="border border-tertiary-blue border-solid w-full mt-2">
        <table
            id="{{$id}}"
            data-content-url="{{$url}}"
            data-identifier-key="{{$item_identifier ?? 'uuid'}}"
            data-label-key="{{$item_label ?? 'uuid'}}"
            data-sort-img-neutral="{{asset('img/icons/sort.svg')}}"
            data-sort-img-desc="{{asset('img/icons/arrow-down.svg')}}"
            data-sort-img-asc="{{asset('img/icons/arrow-up.svg')}}"
            data-empty-body="<tr><td colspan='99' class='text-center'>No Results</td></tr>"
            @if(filter_var($history ?? true, FILTER_VALIDATE_BOOLEAN))data-history="false" @endif
            @if(filter_var($dynamic ?? false, FILTER_VALIDATE_BOOLEAN))data-dynamic-url="true" @endif
            @if(filter_var($selectable ?? false, FILTER_VALIDATE_BOOLEAN))
                data-selection-mode='true'
                data-item-close-button-content="<img src='{{asset('img/icons/close.svg')}}' alt='Delete' class='noPointerEvents'>"
                data-checkbox-header-cls="bg-primary-blue py-1"
            @endif
            @isset($attributes){{$attributes}} @endisset
            class="table dataprovider datatable table-striped text-center w-full"
        >
            <thead>
                <tr class="divide-x border border-tertiary-blue border-solid">
                    {{$slot ?? $headers}}
                </tr>
            </thead>
            <tbody id="{{$id}}-content" class="hidden"></tbody>
        </table>

        {{--| Load spinner |--}}
        <x-datalist.parts.load-spinner dataprovider_id="{{$id}}"/>
    </div>

    {{--| Pagination |--}}
    <x-datalist.parts.pagination id="{{$id}}-pagination" url="{{$url}}/pages" pages_in_pagination="{{$pages_in_pagination ?? 7}}"></x-datalist.parts.pagination>
</div>
