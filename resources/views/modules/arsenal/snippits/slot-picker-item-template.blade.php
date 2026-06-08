<div id="slot-picker-item-template"
     class="card rounded shadow border gray-border p-2 w-24 flex flex-col items-center cursor-pointer hover:bg-gray-50"
     onclick="selectSlotItem(this)">
    <input type="hidden" name="item_uuid">
    <img data-name="icon" data-alt-name="name" class="w-12 h-12 object-contain">
    <div class="flex flex-row items-center justify-center gap-1 mt-1 h-5">
        <img src="{{asset('img/modules/arsenal/icon/potato.png')}}" alt="Potato" title="Orokin Reactor / Catalyst"
             class="mini-icon active hidden"
             data-show-if-true-name="potato">
        <img src="{{asset('img/modules/arsenal/icon/built.png')}}" alt="Built" title="Built"
             class="mini-icon monochrome active hidden"
             data-show-if-true-name="built">
        <img src="{{asset('img/modules/arsenal/icon/owned.png')}}" alt="In loadout" title="Already in a loadout"
             class="mini-icon owned hidden"
             data-show-if-true-name="in_loadout">
    </div>
    <span class="text-xs text-center leading-tight mt-1 break-words w-full" data-name="name"></span>
</div>
