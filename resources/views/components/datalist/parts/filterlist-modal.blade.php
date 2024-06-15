@component('components.modal.modal')
    @slot('id'){{$id}}-filter-modal @endslot
    @slot('body')
        <h3 class="title py-1">Add filters</h3>
        <div id="{{$id}}-filter-form" class="flex gap-2">
            <select name="filter" class=" underlined py-0"></select>
            <select name="operator" class="underlined py-0 hidden"></select>

            {{-- Select --}}
            <select name="select" class="filter-value-select underlined py-0 hidden"></select>

            {{-- Number input --}}
            <input type="number" name="number" class="filter-value-select underlined py-0 hidden">

            {{-- Date input --}}
            <input type="date" name="date" class="filter-value-select underlined py-0 hidden">

            {{-- Dataselect --}}
            <div class="dataselect-container">
                <x-datalist.dataselect.dataselect id="dataselect" name="dataselect" dynamic="true" url="URL"></x-datalist.dataselect.dataselect>
            </div>
        </div>
    @endslot
    @slot('buttons')
        <button type="button" onclick="closeModal({{$id}})" class="cancel_interactive px-5 py-2.5 text-center">Cancel</button>
        <button id="{{$id}}-filter-confirm-button" type="button" class="interactive px-5 py-2.5 text-center" dusk="confirm"
        >Submit</button>
    @endslot
@endcomponent
