@component('components.form.input-wrapper')
    @slot('$input_html')
        <div class="flex flex-col justify-center align-items max-w-xl gap-4">
            @isset($title)
                <h4 class="text-center font-bold text-lg w-full">{{$title}}</h4>
            @endisset
            <div id="{{$id}}-select-list"
                 class="flex justify-center align-items flex-wrap grow  border border-tertiary-blue border-solid rounded-lg w-full max-h-64 p-3 gap-1"
                 @isset($selection_url)data-selection-url="{{$selection_url}}" @endisset
                 @if(($readonly ?? false) === true)data-readonly="true" @endif
                 data-item-identifier="{{$item_identifier ?? 'uuid'}}"
                 data-item-label="{{$item_label ?? 'uuid'}}"
                 data-item-cls="border-solid border-black border rounded-lg flex justify-center items-center gap-2 px-2 py-1"
                 data-item-close-button-content="<img src='{{asset('img/icons/close.svg')}}' alt='Verwijder' class='noPointerEvents'>"
                 data-checkbox-header-cls="bg-primary-blue py-1">
            </div>

            {{--            @include('components.dataproviders.datatable', [--}}
            {{--                'headers' => $headers,--}}
            {{--                'pagesInPagination' => $pagesInPagination ?? 4,--}}
            {{--                'history' => false,--}}
            {{--            ])--}}
        </div>
    @endslot
@endcomponent


