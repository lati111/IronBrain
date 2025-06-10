@props([
    'name',
    'value',
    'save_callback',
    'cls',
    'display_cls',
    'edit_cls',
    'label_text',
    'label_name',
])

<x-form.input-wrapper name="{{$name}}" label_text="{{$label_text}}" label_name="{{$label_name ?? ''}}">
    @component('components.form.editable-inputs.toggleable-edit-field')
        @slot('name', $name)
        @slot('display')
            <span class="display text-center inline whitespace-pre-wrap {{$cls ?? ''}} {{$display_cls ?? ''}}" data-name="{{$name}}">{{$value ?? ''}}</span>
        @endslot
        @slot('input')
            <span class="display text-center inline {{$cls ?? ''}} {{$edit_cls ?? ''}}">{{$slot}}</span>
        @endslot
        @slot('save_method', sprintf('saveTextAreaEdit(this.closest(`.edit-container`), %s)', $save_callback))
    @endcomponent
</x-form.input-wrapper>

