@component('components.modal.modal')
    @slot('id'){{$id}} @endslot
    @slot('body')
        <h3 class="title py-1">{{$title}}</h3>
        <select name="{{$name}}" class="mediumInput underlined py-0">
            {{$options}}
        </select>
    @endslot
    @slot('buttons')
        <button type="button" onclick="closeModal()" class="cancel_interactive px-5 py-2.5 text-center">Cancel</button>
        <button type="button" onclick="store_modal_data(this.closest('#{{$id}}')); closeModal(); {{$submit_function}}" class="interactive px-5 py-2.5 text-center">Submit</button>
    @endslot
@endcomponent
