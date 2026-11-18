<button class="filter-btn w-8 h-8 flex items-center justify-center rounded transition-colors {{($selected ?? false) ? 'selected' : ''}}"
        data-filter-group="{{$filterGroup}}" data-filter-value="{{$value}}" title="{{$title}}">
    <img src="{{$slot->hasActualContent() ? $slot : $src}}" alt="{{$title}}">
</button>
