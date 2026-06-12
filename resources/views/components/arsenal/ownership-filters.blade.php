<x-datalist.filters.filter-group>
    <x-datalist.filters.filter-item title="All" filter-group="variant" value="all" :selected="true">{{asset('img/modules/arsenal/icon/all.png')}}</x-datalist.filters.filter-item>
    <x-datalist.filters.filter-item title="Prime" filter-group="variant" value="prime">{{asset('img/modules/arsenal/icon/prime.png')}}</x-datalist.filters.filter-item>
    <x-datalist.filters.filter-item title="Mundane" filter-group="variant" value="non-prime">{{asset('img/modules/arsenal/icon/mundane.png')}}</x-datalist.filters.filter-item>
</x-datalist.filters.filter-group>

<x-datalist.filters.filter-group>
    <x-datalist.filters.filter-item title="All" filter-group="ownership" value="all" :selected="true">{{asset('img/modules/arsenal/icon/all.png')}}</x-datalist.filters.filter-item>
    <x-datalist.filters.filter-item title="Owned" filter-group="ownership" value="owned">{{asset('img/modules/arsenal/icon/owned.png')}}</x-datalist.filters.filter-item>
    <x-datalist.filters.filter-item title="Unowned" filter-group="ownership" value="unowned">{{asset('img/modules/arsenal/icon/unowned.png')}}</x-datalist.filters.filter-item>
</x-datalist.filters.filter-group>
