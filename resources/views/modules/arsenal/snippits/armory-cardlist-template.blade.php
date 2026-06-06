<div id="armory-cardlist-template" class="card rounded shadow border gray-border p-3 w-[8.5rem] flex flex-col">
    <input type="hidden" name="item_id">
    <input type="hidden" name="item_type">

    {{--| Item display (greyed out when unowned) |--}}
    <div class="flex flex-col items-center gap-1 flex-1"
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

    {{--| Add button (shown only for unowned items) |--}}
    <div class="flex justify-center mt-2 hidden" data-show-if-true-name="unowned">
        <button class="interactive text-xs px-3 py-1 w-full"
                onclick="addItem(this.closest('#armory-cardlist-template'))">
            + Add
        </button>
    </div>
</div>
