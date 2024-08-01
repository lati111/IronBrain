@props([
    'dataprovider_id',
    'cls',
])

<div id="{{$dataprovider_id}}-template" class="card flex flex-col justify-center rounded shadow border gray-border p-3 {{$cls ?? ''}}">
    {{$slot}}
</div>
