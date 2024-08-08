@props([
    'id',
    'name',
    'onclick',
    'value',
    'left_label',
    'right_label'
])

<div class="flex items-center justify-center">
    <label id="{{$id ?? $name}}">

        @isset($left_label)
            <span class="mr-2 text-sm" >{{$left_label}}</span>
        @endisset

        <input id="{{$id ?? $name}}" type="checkbox" name="{{$name}}" class="w-4 h-4 text-red-600 focus:ring-red-500 {{$cls ?? ''}}" {{ $attributes ?? '' }}
            @isset($value)value="{{$value}}" @endisset
            @isset($onclick)onclick="{{$onclick}}" @endisset
        />

        @if($slot->hasActualContent() || ($right_label ?? null) !== null)
            <span class="ml-2 text-sm">{{$slot->hasActualContent() ? $slot : $right_label}}</span>
        @endif
    </label>
</div>
