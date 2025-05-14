@props([
    'label_text',
    'label_name',
    'input_html',
    'cls',
])

<div class="flex flex-col {{$cls ?? ''}}">
    <label class="text-sm ml-3 form_label" @isset($label_name)data-name="{{$label_name}}" @endisset>{{$label_text}}</label>
    {{$slot->hasActualContent() ? $slot : $input_html}}
</div>
