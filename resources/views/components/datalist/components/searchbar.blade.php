<div class="flex flex-row justify-center pt-2">
    <input id="{{$dataproviderID}}-searchbar" type="text" name="search" class="dataprovider-searchbar underlined text-center w-72 ml-16" placeholder="Search..."
        data-searchfields="{{$searchfields}}" onkeydown="searchbarEnterListener(event, '{{$dataproviderID}}')"
        @isset($search)value="{{$search}}"@endisset>
    <button class="interactive pl-2" onclick="loadDataprovider('{{$dataproviderID}}')">Search</button>
</div>
