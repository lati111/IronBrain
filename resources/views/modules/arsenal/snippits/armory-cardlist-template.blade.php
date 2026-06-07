<div id="armory-cardlist-template" class="card rounded shadow border gray-border p-3 w-[8.5rem] flex flex-col cursor-pointer"
     onclick="openItemModal(this)">
    <input type="hidden" name="item_id">
    <input type="hidden" name="item_type">
    <input type="hidden" name="user_uuid">
    <input type="hidden" name="forma">
    <input type="hidden" name="potato">
    <input type="hidden" name="built">
    <input type="hidden" name="riven">
    <input type="hidden" name="school">
    <input type="hidden" name="school_abbr">

    {{--| Main row: item display + property indicators |--}}
    <div class="flex flex-row gap-1 flex-1">

        {{--| Item display (greyed out when unowned) |--}}
        <div class="flex flex-col items-center gap-1 flex-1 min-w-0"
             data-add-class-if-true-name="unowned" data-class-to-add="opacity-40">
            <span class="text-[0.6rem] text-gray-400 font-medium uppercase tracking-wide text-center"
                  data-name="category_display"></span>
            <img data-name="icon" data-alt-name="name"
                 class="w-14 h-14 object-contain mt-1 mx-auto">
            <span class="text-xs text-center leading-tight font-medium mt-1 break-words w-full"
                  data-name="name"></span>
            <span class="text-[0.65rem] text-green-600 font-medium hidden mt-auto"
                  data-show-if-true-name="owned">Owned</span>
        </div>

        {{--| Property indicators (owned only) |--}}
        <div class="flex flex-col gap-1 items-center justify-center flex-shrink-0 hidden"
             data-show-if-true-name="owned">

            {{-- Forma --}}
            <div class="flex flex-col items-center leading-none gap-0.5">
                <span class="text-[0.7rem] font-bold text-gray-600 leading-none" data-name="forma"></span>
                <span class="text-[0.45rem] text-gray-400 uppercase tracking-wide leading-none">f</span>
            </div>

            {{-- Potato --}}
            <span class="text-[0.6rem] font-bold text-gray-300"
                  data-add-class-if-true-name="potato"
                  data-class-to-add="!text-amber-400"
                  title="Orokin Reactor / Catalyst">P</span>

            {{-- Built --}}
            <span class="text-[0.6rem] font-bold text-gray-300"
                  data-add-class-if-true-name="built"
                  data-class-to-add="!text-green-500"
                  title="Built">B</span>

            {{-- Riven --}}
            <span class="text-[0.6rem] font-bold text-gray-300"
                  data-add-class-if-true-name="riven"
                  data-class-to-add="!text-purple-400"
                  title="Riven">R</span>

            {{-- Focus School --}}
            <span class="text-[0.6rem] font-bold text-gray-500 uppercase leading-none"
                  data-name="school_abbr"
                  title="Focus School"></span>

        </div>

    </div>

    {{--| Add button (shown only for unowned items) |--}}
    <div class="flex justify-center mt-2 hidden" data-show-if-true-name="unowned">
        <button class="interactive text-xs px-3 py-1 w-full"
                onclick="addItem(this.closest('#armory-cardlist-template')); event.stopPropagation()">
            + Add
        </button>
    </div>
</div>
