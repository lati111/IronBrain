<div data-equipment="{{$category}}" class="loadout-equipment flex flex-col items-center cursor-pointer rounded hover:bg-gray-50 px-2 py-1 h-[95px]"
     onclick="openSlotModal(this, '{{$category}}')">
    <span class="text-xs text-gray-400 font-medium uppercase tracking-wide whitespace-nowrap">{{$text}}</span>
    <div class="hidden mt-1" data-show-if-true-name="has_{{$category}}">
        <img data-name="{{$category}}_icon" data-alt-name="{{$category}}_name" class="w-12 h-12 object-contain">
    </div>
    <div class="w-12 h-12 bg-gray-100 rounded flex items-center justify-center mt-1" data-hide-if-true-name="has_{{$category}}">
        <span class="text-gray-300">—</span>
    </div>
    <span class="arsenal-slot-name text-xs text-center leading-tight mt-1" data-name="{{$category}}_name"></span>
</div>
