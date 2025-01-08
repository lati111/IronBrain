<div id="description-container" class="text-center">
    <span id="shortened-description-container">
        <span id="shortened-description"></span>

        <x-elements.buttons.button cls="text-4xl leading-3 h-4 ml-1" onclick="expandShortenedDescription(this.closest(`#description-container`))">...</x-elements.buttons.button>
    </span>

    <span id="full-description-container" class="hidden">
        <span id="full-description"></span>

        <x-elements.buttons.icon-button src="{{asset('img/icons/show-less.svg')}}" alt="collapse" cls="text-4xl leading-3 h-4 ml-1" onclick="collapseShortenedDescription(this.closest(`#description-container`))"/>
    </span>
</div>
