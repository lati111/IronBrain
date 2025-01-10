<select name="{{$name}}" class="underlined h-8 w-{{$width ?? 72}} py-0.5">
    {{$slot->hasActualContent() ? $slot : $options}}
</select>
