<div class="flex items-center justify-center">
    <label for="{{$name}}"
        @if (isset($id))
            for="{{$id}}"
        @else
            for="{{$name}}"
        @endif
    />
        @isset($left_label)
            <span for="default-checkbox" class="mr-2 text-sm" name="in_nav">{{$left_label}}</span>
        @endisset
        <input
            type="checkbox" name="{{$name}}" class="w-4 h-4 text-red-600 focus:ring-red-500" {{ $attributes ?? '' }}
            @if (isset($id))
                id="{{$id}}"
            @else
                id="{{$name}}"
            @endif
        />
        @isset($right_label)
            <span for="default-checkbox" class="ml-2 text-sm" name="in_nav">{{$right_label}}</span>
        @endisset
    </label>
</div>
