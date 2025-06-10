<button type="button" class="icon-text-btn {{$cls ?? ''}}" @isset($onclick)onclick="{{$onclick}}" @endisset>
    <img src="{{$src}}" alt="{{$slot->hasActualContent() ? $slot : $alt}}" class="icon">
    <span>{{$slot->hasActualContent() ? $slot : $content}}</span>
</button>
