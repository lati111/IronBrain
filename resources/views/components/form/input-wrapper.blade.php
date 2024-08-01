@props([
    'label_text',
    'input_html',
    'cls',
])

<div class="flex flex-col {{$cls ?? ''}}">
    <label class="text-sm ml-3 form_label">{{$label_text}}</label>
    {{$slot->hasActualContent() ? $slot : $input_html}}
</div>
