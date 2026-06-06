<div id="slot-picker-item-template"
     class="card rounded shadow border gray-border p-2 w-24 flex flex-col items-center cursor-pointer hover:bg-gray-50"
     onclick="selectSlotItem(this)">
    <input type="hidden" name="item_uuid">
    <img data-name="icon" data-alt-name="name" class="w-12 h-12 object-contain">
    <span class="text-xs text-center leading-tight mt-1 break-words w-full" data-name="name"></span>
</div>
