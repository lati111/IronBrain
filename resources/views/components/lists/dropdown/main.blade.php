<button id="{{$id}}-button" data-dropdown-toggle="{{$id}}-dropdown" class="interactive px-2 py-1 text-center inline-flex items-center {{$cls ?? ''}}" type="button">
    {{$title}}
    <svg class="w-2.5 h-2.5 ms-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 10 6">
        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 4 4 4-4"/>
    </svg>
</button>

<div id="{{$id}}-dropdown" class="z-10 hidden bg-white divide-y divide-gray-100 rounded-lg shadow w-44 dark:bg-gray-700">
    <ul class="py-2 text-gray-700 dark:text-gray-200" aria-labelledby="{{$id}}-button">
        {{$slot}}
    </ul>
</div>
