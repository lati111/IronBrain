@component('components.modal.filterlist_modal')
    @slot("id", "pksanc-box-cardlist-filter-modal")
    @slot("title", "Add a filter")
    @slot("submit_function", "console.log")
@endcomponent

<div id="{{$dataproviderID}}-filterlist" class="dataprovider-filterlist">
    <div class="filter-container flex flex-row justify-center pt-2 gap-2">
        <button type="button" class="interactive pl-2" onclick="openFilterlist('{{$dataproviderID}}', 'pksanc-box-cardlist-filter-modal', '{{route($route)}}')">
            Add Filter
        </button>
    </div>
</div>

