@props([
    'column',
    'width',
    'visible',
    'sortable',
    'default',
    'wrapper_cls',
    'cell_cls',
    'format',
    'display',
])

<th class="datatable-header py-1 @if(filter_var($visible ?? true, FILTER_VALIDATE_BOOLEAN) === false)hidden @endif"
    style="@isset($width)width:{{$width}}%; @endisset"
    @isset($width)data-size="{{$width}}" @endisset
    @isset($wrapper_cls)data-wrapper-cls='{{$wrapper_cls}}' @endisset
    @isset($cell_cls)data-cell-cls='{{$cell_cls}}' @endisset
    @isset($default)data-default='{{$default}}' @endisset
    @if(filter_var($sortable ?? false, FILTER_VALIDATE_BOOLEAN))
        data-sortable="true"
        data-sort-dir="neutral"
    @endif
    @if($slot->isNotEmpty() || isset($format))data-format='{{$slot ?? $format}}' @endif
    data-visible="{{filter_var($visible ?? true, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false'}}"
    data-column="{{$column}}">

    <div class="flex justify-center items-center gap-3 @if(($sortable ?? false) === true)cursor-pointer @endif">
        @isset($display)<span>{{$display}}</span>@endisset

        @if(filter_var($sortable ?? false, FILTER_VALIDATE_BOOLEAN))
            <span><img class="sort-image" src="{{asset('img/icons/sort.svg')}}" alt="neutral sort icon"></span>
        @endif
    </div>
</th>
