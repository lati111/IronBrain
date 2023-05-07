<div id="{{$id}}" tabindex="-1"
    class="fixed top-0 left-0 right-0 z-50 hidden p-4 overflow-x-hidden overflow-y-auto md:inset-0 h-[calc(100%-1rem)] max-h-full">
    <div class="relative w-full max-w-md max-h-full">
        <div class="relative bg-white rounded-lg shadow">
            <button type="button"
                class="absolute top-3 right-2.5 interactive rounded-lg text-sm p-1.5 ml-auto inline-flex items-center" onclick="closeModal()">
                <svg aria-hidden="true" class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"
                    xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd"
                        d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                        clip-rule="evenodd">
                    </path>
                </svg>
                <span class="sr-only">Close modal</span>
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
