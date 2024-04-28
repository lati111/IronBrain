<div id="{{$id}}-disable-container" class="flex flex-col gap-2 justify-center max-w-screen-2xl w-full">
    <div class="flex justify-center mb-6">
        <div class="">
            {{$buttons_left ?? ''}}
            <x-datalist.parts.searchbar id="{{$id}}"></x-datalist.parts.searchbar>
            {{$buttons_center_left ?? ''}}
        </div>
    </div>

    <div class="hidden">
        {{$buttons_center_right ?? ''}}
        @component('components.datalist.parts.perpage_select')
            @slot('id', $id.'-pagination-perpage-selector')
            @slot('selected_option', 9)
            @slot('options', [3,6,9,12,18,30])
        @endcomponent
        {{$buttons_right ?? ''}}
    </div>
    <div class="template hidden">{{$slot}}</div>

    <div
        id="{{$id}}"
        data-content-url="{{$url}}"
        data-pagination-ID="{{$id}}-pagination"
        data-searchbar-ID="{{$id}}-searchbar"
        data-empty-body="<p><class='text-center w-full'>Geen resultaten</p>"
        @if(($history ?? true) === false || ($history ?? 'true') === 'false')data-history="false" @endif
        @if(($dynamic ?? false) === true || ($dynamic ?? 'false') === 'true')data-dynamic-url="true" @endif
        @isset($activity_column)data-activity-key="{{$activity_column}}" @endisset
        class="dataprovider cardlist text-center w-full"
    >
        <div id="{{$id}}-spinner" class="spinner flex justify-center py-6">
            <img src="{{asset('img/icons/loading.svg')}}" class="animate-spin" alt="bezig met laden">
        </div>

        <div id="{{$id}}-content" class="hidden flex justify-center flex-wrap gap-6"></div>
    </div>

    <x-datalist.parts.pagination id="{{$id}}-pagination" url="{{$url}}/pages" pages_in_pagination="{{$pagesInPagination ?? 7}}"></x-datalist.parts.pagination>
</div>
