@props([
    'name',
    'value',
    'save_callback',
    'display_cls',
    'label_text'
])
<x-form.input-wrapper name="{{$name}}" label_text="{{$label_text}}">
    @component('components.form.editable-inputs.toggleable-edit-field')
        @slot('name', $name)
        @slot('display')
            <span class="display text-center inline {{$display_cls ?? ''}}">{{$value ?? ''}}</span>
        @endslot
        @slot('input')
            {{$slot}}
        @endslot
        @slot('save_method', sprintf('saveTextAreaEdit(this.closest(`.edit-container`), %s)', $save_callback))
    @endcomponent
</x-form.input-wrapper>

