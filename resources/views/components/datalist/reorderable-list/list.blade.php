<div class="reorderable-list-container">
    @isset($title)
        <h4 class="title">{{$title}}</h4>
    @endisset

    <x-elements.buttons.icon-button src="{{asset('img/icons/plus.svg')}}" alt="Add" cls="mt-2 float-right"></x-elements.buttons.icon-button>

    {{--| Main datalist |--}}
    <div
        id="{{$id}}"
        class="dataprovider reorderable-list-items flex flex-col justify-center items-center gap-4"
        data-content-url="{{$url}}"
        data-empty-body="<p><class='text-center w-full'>No results</p>"
        @if(filter_var($history ?? true, FILTER_VALIDATE_BOOLEAN) === false)data-history="false" @endif
        @if(filter_var($dynamic ?? false, FILTER_VALIDATE_BOOLEAN))data-dynamic-url="true" @endif
        @if(filter_var($filtering ?? false, FILTER_VALIDATE_BOOLEAN))
            data-option-content-cls="block p-2 border-transparent border-l-4 group-hover:border-red-600 group-hover:bg-gray-100 h-9"
            data-filter-item-container-cls="underlined flex gap-2 justify-center px-2"
            data-filter-delete-button-content="<img src='{{asset('img/icons/x.svg')}}' alt='delete' class='interactive w-5 h-5'>"
        @endif
    >
        {{--| Load spinner |--}}
        <x-datalist.parts.load-spinner dataprovider_id="{{$id}}"/>

        {{--| Datalist body |--}}
        <div id="{{$id}}-content" class="flex flex-col justify-center flex-wrap gap-6"></div>
    </div>
</div>


