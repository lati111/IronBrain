@props([
    'label_text'
])

<div class="edit-container flex justify-center items-center" onmouseover="revealEditButton(this)" onmouseout="hideEditButton(this)">
    <div class="display-format">
        <div class="flex justify-center gap-2">
            <div class="w-6"></div>
            {{$display}}
            <x-elements.buttons.icon-button src="{{asset('img/icons/edit.svg')}}" alt="edit" onclick="toggleEditMode(this.closest(`.edit-container`), true)" cls="edit-btn opacity-0"/>
        </div>
    </div>

    <div class="input-format hidden">
        <div class="flex flex-col justify-center items-center gap-2">
            @if(isset($wrapper_text))
                <x-form.input-wrapper label_text="{{$wrapper_text}}" name="{{$name}}">{{$input}}</x-form.input-wrapper>
            @else
                {{$input}}
            @endif

            <div>
                <x-elements.buttons.button onclick="{{$save_method}}">Save</x-elements.buttons.button>

                <span class="mx-0.5">or</span>

                <x-elements.buttons.button onclick="toggleEditMode(this.closest(`.edit-container`), false)">Cancel</x-elements.buttons.button>
            </div>
        </div>
    </div>
</div>
