<button type="button" class="interactive {{$cls ?? ''}}" @isset($onclick)onclick="{{$onclick}}" @endisset>{{$slot}}</button>
