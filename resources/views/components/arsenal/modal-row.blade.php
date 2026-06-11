@props(['id' => null, 'label', 'labelId' => null, 'hidden' => false])

<div @if($id) id="{{$id}}" @endif class="arsenal-modal-row {{ $hidden ? 'hidden' : '' }}">
    <label @if($labelId) id="{{$labelId}}" @endif class="arsenal-modal-label">{{$label}}</label>
    {{ $slot }}
</div>
