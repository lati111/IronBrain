@props(['category', 'text'])

<div data-equipment="{{$category}}"
     class="arsenal-equipment-slot"
     onclick="openSlotModal(this, '{{$category}}')">
    <span class="arsenal-slot-label">{{$text}}</span>
    <div class="hidden mt-1" data-show-if-true-name="has_{{$category}}">
        <img data-name="{{$category}}_icon" data-alt-name="{{$category}}_name" class="w-12 h-12 object-contain">
    </div>
    <div class="w-12 h-12 bg-gray-100 rounded flex items-center justify-center mt-1" data-hide-if-true-name="has_{{$category}}">
        <span class="text-gray-300">—</span>
    </div>
    <span class="arsenal-slot-name text-xs text-center leading-tight mt-1" data-name="{{$category}}_name"></span>
</div>
