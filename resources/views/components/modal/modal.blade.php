<div id="{{$id}}" tabindex="-1"
    class="fixed top-0 left-0 right-0 z-50 hidden p-4 overflow-x-hidden overflow-y-auto md:inset-0 h-[calc(100%-1rem)] max-h-full">
    <div class="relative" style="min-width:28em">
        <div class="relative bg-white rounded-lg shadow">
            <button type="button" class="absolute top-3 right-2.5 p-1.5 ml-auto" onclick="closeModal()">
                <img src="{{asset('img/icons/x.svg')}}" alt="close" class="interactive w-5 h-5">
            </button>
            <div class="p-6 space-y-6 flex flex-col justify-center items-center">
                {{$body}}
            </div>
            <div class="flex justify-center items-center space-x-2 py-1 border-t">
                {{$buttons}}
            </div>
        </div>
    </div>
</div>
