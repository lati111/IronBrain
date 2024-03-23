@component('components.modal.modal')
    @slot('id'){{$id}} @endslot
    @slot('body')
        <h3 class="title py-1">{{$title}}</h3>
        <div id="filter-input-list" class="flex gap-2">
            <select name="bool-select" class=" underlined py-0 hidden"></select>
            <select name="filter-select" class=" underlined py-0"></select>
            <select name="operator-select" class="underlined py-0 hidden"></select>
            <select name="value-select" class="underlined py-0 hidden"></select>
            <input name="number-input" type="number" class="underlined py-0 hidden">
            <input name="date-input" type="date" class="underlined py-0 hidden">
        </div>
    @endslot
    @slot('buttons')
        <button type="button" onclick="closeModal({{$id}})" class="cancel_interactive px-5 py-2.5 text-center">Cancel</button>
        <button type="button" onclick="addFilter()"
            class="interactive px-5 py-2.5 text-center" dusk="confirm"
            >Submit</button>
    @endslot
@endcomponent
