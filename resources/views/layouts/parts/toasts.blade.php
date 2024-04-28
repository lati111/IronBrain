<div id="toasts" class="absolute flex flex-col gap-2 top-3 left-3 w-64" dusk="toasts">
    @if ($error = Session::get('error'))
        @component('components.toast')
            @slot('id') error-toast-0 @endslot
            @slot('text') {{$error}} @endslot
            @slot('class') error-toast @endslot
        @endcomponent
    @endif

    @foreach ($errors->all() as $error)
        @component('components.toast')
            @slot('id') error-toast-{{$loop->index + 1}} @endslot
            @slot('text') {{$error}} @endslot
            @slot('class') error-toast @endslot
        @endcomponent
    @endforeach

    @if ($message = Session::get('message'))
        @component('components.toast')
            @slot('id') message-toast-0 @endslot
            @slot('text') {{$message}} @endslot
            @slot('class') message-toast @endslot
        @endcomponent
    @endif
</div>
