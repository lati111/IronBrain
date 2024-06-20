<label class="flex w-full @isset($container_cls){{$container_cls}} @endisset">
    <div class="relative w-full">
        <div class="flex items-center underlined h-8">
            <input
                id="{{$id}}"
                name="{{$name}}"
                type="hidden"
                data-content-url="{{$url}}"
                data-expand-button-id="{{$id}}-expand-button"
                data-collapse-button-id="{{$id}}-collapse-button"
                data-searchbar-ID="{{$id}}-searchbar"
                data-identifier-key="{{$identifier  ?? 'uuid'}}"
                data-label-key="{{$label ?? 'uuid'}}"
                @if(($dynamic ?? false) === true || ($dynamic ?? 'false') === 'true')data-dynamic-url="true" @endif
                @if(($dynamicloading ?? true) === false || ($dynamicloading ?? 'true') === 'false')data-dynamic-loading="false" @endif
                data-option-cls="cursor-pointer group"
                data-option-content-cls="block p-2 border-transparent border-l-4 group-hover:border-red-600 group-hover:bg-gray-100 h-9"
                class="dataprovider"
            />

            <input id="{{$id}}-searchbar" class="searchbar px-4 outline-none w-full border-none h-8" placeholder="..." autocomplete="off">

            <label class="cursor-pointer outline-none focus:outline-none border-l border-gray-200 transition-all px-1">
                <button id="{{$id}}-expand-button"><img src="{{asset('img/icons/show-more.svg')}}" alt="show more" class="interactive"></button>
                <button id="{{$id}}-collapse-button"><img src="{{asset('img/icons/show-less.svg')}}" alt="show less" class="interactive"></button>
            </label>
        </div>

        <div id="{{$id}}-content" class="absolute rounded shadow bg-white overflow-x-hidden overflow-y-scroll w-full max-h-72">
        </div>

        <div id="{{$id}}-spinner" class="spinner absolute rounded shadow bg-white overflow-hidden w-full">
            <div id="{{$id}}-spinner" class="spinner flex justify-center py-6">
                <img src="{{asset('img/icons/loading.svg')}}" class="animate-spin" alt="loading">
            </div>
        </div>
    </div>
</label>


