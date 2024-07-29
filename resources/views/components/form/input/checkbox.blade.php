<div class="flex items-center justify-center">
    <label id="{{$id ?? $name}}">

        @isset($left_label)
            <span class="mr-2 text-sm" >{{$left_label}}</span>
        @endisset

        <input id="{{$id ?? $name}}" type="checkbox" name="{{$name}}" class="w-4 h-4 text-red-600 focus:ring-red-500" {{ $attributes ?? '' }}
            @isset($value)value="{{$value}}" @endisset
            @isset($onclick)onclick="{{$onclick}}" @endisset
        />

        @isset($right_label)
            <span class="ml-2 text-sm">{{$right_label}}</span>
        @endisset
    </label>
</div>
