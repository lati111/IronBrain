<input
    type='file'
    name='{{$name}}'
    class='file-uploader relative underlined'
    @isset($required) @if ($required === true) required @endif @endisset
    @isset($accepts)
        accept="{{$accepts}}"
    @endisset
/>
