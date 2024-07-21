<th class="datatable-header py-1
    @if(($visible ?? true) === false || ($visible ?? 'true') === 'false')hidden @endif"
    style="@isset($width)width:{{$width}}%; @endisset"
    @isset($width)data-size="{{$width}}" @endisset
    @isset($wrapper_cls)data-wrapper-cls='{{$wrapper_cls}}' @endisset
    @isset($cell_cls)data-cell-cls='{{$cell_cls}}' @endisset
    @isset($default)data-default='{{$default}}' @endisset
    @isset($sortable)
        @if($sortable === true || $sortable === 'true')
            data-sortable="true"
            data-sort-dir="neutral"
        @endif
    @endisset
    @if($slot->isNotEmpty() || isset($format))data-format='{{$slot ?? $format}}' @endif
    data-visible="{{(($visible ?? true) === false || ($visible ?? 'true') === 'false') ? 'false' : 'true'}}"
    data-column="{{$column}}">

    <div class="flex justify-center items-center gap-3 @if(($sortable ?? false) === true)cursor-pointer @endif">
        @isset($display)<span>{{$display}}</span>@endisset
        @isset($sortable)
            @if($sortable === true || $sortable === 'true')
                <span><img class="sort-image" src="{{asset('img/icons/sort.svg')}}" alt="neutral sort icon"></span>
            @endif
        @endisset
    </div>
</th>
