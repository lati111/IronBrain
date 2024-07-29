<div class="flex justify-center items-center h-8" data-confirm-button-ID="{{$id}}-confirm-button">
    <div class="flex flex-row justify-center pt-2">
        <input id="{{$id}}-searchbar" type="text" name="search" class="searchbar underlined text-center w-72 h-8 ml-16" placeholder="Search..."
               @isset($search)value="{{$search}}"@endisset>
        <button id="{{$id}}-search-confirm-button" class="interactive pl-2">Search</button>
    </div>
</div>
