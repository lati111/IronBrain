@props([
    'name',
    'value',
    'save_callback',
    'display_cls',
    'label_text'
])

@component('components.form.editable-inputs.toggleable-edit-field')
    @slot('wrapper_text', $label_text)
    @slot('name', $name)
    @slot('display')
        <span class="display text-center inline {{$display_cls ?? ''}}">{{html_entity_decode($value) ?? ''}}</span>
    @endslot
    @slot('input')
        {{$slot}}
    @endslot
    @slot('save_method', sprintf('saveTextEdit(this.closest(`.edit-container`), %s)', $save_callback))
@endcomponent
