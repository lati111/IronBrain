@props([
    'id',
    'name',
    'url',
    'item_label',
    'item_identifier',
    'dynamic',
    'dynamic_loading',
    'container_cls',
])

<label class="flex w-full @isset($container_cls){{$container_cls}} @endisset">
    <div class="relative w-full">
        <div class="flex items-center underlined h-8">
            <input
                id="{{$id}}"
                name="{{$name}}"
                type="hidden"
                class="dataprovider"
                data-content-url="{{$url}}"
                data-identifier-key="{{$item_identifier ?? 'uuid'}}"
                data-label-key="{{$item_label ?? 'uuid'}}"
                @if(filter_var($dynamic ?? false, FILTER_VALIDATE_BOOLEAN))data-dynamic-url="true" @endif
                @if(filter_var($dynamic_loading ?? false, FILTER_VALIDATE_BOOLEAN))data-dynamic-loading="false" @endif
                data-option-cls="cursor-pointer group"
                data-option-content-cls="block p-2 border-transparent border-l-4 group-hover:border-red-600 group-hover:bg-gray-100 h-9"
            />

            <input id="{{$id}}-searchbar" class="searchbar px-4 outline-none w-full border-none h-8" placeholder="..." autocomplete="off">

            <label class="cursor-pointer outline-none focus:outline-none border-l border-gray-200 transition-all px-1">
                <button type="button" id="{{$id}}-expand-button"><img src="{{asset('img/icons/show-more.svg')}}" alt="show more" class="interactive"></button>
                <button type="button" id="{{$id}}-collapse-button"><img src="{{asset('img/icons/show-less.svg')}}" alt="show less" class="interactive"></button>
            </label>
        </div>

        <div id="{{$id}}-content" class="absolute rounded shadow bg-white overflow-x-hidden overflow-y-scroll w-full max-h-72">
        </div>

        <div id="{{$id}}-spinner" class="spinner absolute rounded shadow bg-white overflow-hidden w-full">
            <x-datalist.parts.load-spinner dataprovider_id="{{$id}}"/>
        </div>
    </div>
</label>


