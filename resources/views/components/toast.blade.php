<div id="{{$id}}" class="@isset($class){{$class}}@endisset flex items-center w-full max-w-xs p-3 bg-white rounded-lg shadow" role="alert">
    <div class="ml-3 text-sm">{{$text}}</div>
    <button type="button" class="ml-auto -mx-1.5 -my-1.5 bg-white interactive rounded-lg p-1.5 inline-flex h-8 w-8"
        data-dismiss-target="#{{$id}}" aria-label="Close">

        <span class="sr-only">Close</span>
        <svg aria-hidden="true" class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
        </svg>
    </button>
</div>
