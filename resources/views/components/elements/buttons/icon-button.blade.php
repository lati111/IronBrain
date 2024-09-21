<button type="button" class="{{$cls ?? ''}}" @isset($onclick)onclick="{{$onclick}}" @endisset>
    <img src="{{$src}}" alt="{{$alt}}" class="interactive inline w-6 h-6">
</button>
