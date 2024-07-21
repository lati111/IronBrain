@props([
    'id',
    'data_url',
])

{{--| Container |--}}
<div id="{{$id}}-disable-container" class="flex flex-col gap-2 justify-center max-w-screen-2xl w-full">
    {{--| Filter list modal |--}}
    @if(($filtering ?? false) === true || ($filtering ?? 'false') === 'true')
        <x-datalist.parts.filterlist-modal id="{{$id}}"></x-datalist.parts.filterlist-modal>
    @endif

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

    <div class="border border-tertiary-blue border-solid w-full mt-2">
        <table
            id="{{$id}}"
            data-content-url="{{$data_url}}"
            data-pagination-ID="{{$id}}-pagination"
            data-searchbar-ID="{{$id}}-searchbar"
            data-identifier-key="{{$item_identifier ?? 'uuid'}}"
            data-label-key="{{$item_label ?? 'uuid'}}"
            data-sort-img-neutral="{{asset('img/icons/sort.svg')}}"
            data-sort-img-desc="{{asset('img/icons/arrow-down.svg')}}"
            data-sort-img-asc="{{asset('img/icons/arrow-up.svg')}}"
            data-empty-body="<tr><td colspan='99' class='text-center'>No Results</td></tr>"
            @if(($history ?? true) === false || ($history ?? 'true') === 'false')data-history="false" @endif
            @if(($dynamic ?? false) === true || ($dynamic ?? 'false') === 'true')data-dynamic-url="true" @endif
            @if(($selectable ?? false) === true || ($selectable ?? 'false') === 'true')
                data-selection-mode='true'
                data-item-close-button-content="<img src='{{asset('img/icons/close.svg')}}' alt='Verwijder' class='noPointerEvents'>"
                data-checkbox-header-cls="bg-primary-blue py-1"
            @endif
            @isset($attributes){{$attributes}} @endisset
            @yield($id.'-attributes')
            class="table dataprovider datatable table-striped text-center w-full"
        >
            <thead>
            <tr class="divide-x border border-tertiary-blue border-solid">
                {{$slot ?? $headers}}
            </tr>
            </thead>
            <tbody id="{{$id}}-content" class="hidden">
            </tbody>
        </table>

        {{--| Load spinner |--}}
        <div id="{{$id}}-spinner" class="spinner flex justify-center py-6">
            <img src="{{asset('img/icons/loading.svg')}}" class="animate-spin" alt="loading">
        </div>
    </div>


    {{--| Pagination |--}}
    <x-datalist.parts.pagination id="{{$id}}-pagination" url="{{$data_url}}/pages" pages_in_pagination="{{$pagesInPagination ?? 7}}"></x-datalist.parts.pagination>
</div>
