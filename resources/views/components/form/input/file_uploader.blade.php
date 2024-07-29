<input type='file' name='{{$name}}' class='file-uploader relative underlined {{$cls ?? ''}}'
    @if(filter_var($required ?? false, FILTER_VALIDATE_BOOLEAN))required @endif
    @isset($accepts)accept="{{$accepts}}" @endisset
/>
