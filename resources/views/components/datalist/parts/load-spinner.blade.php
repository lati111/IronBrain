@props(['dataprovider_id'])

<div id="{{$dataprovider_id}}-spinner" class="spinner flex justify-center py-6">
    <img src="{{asset('img/icons/loading.svg')}}" class="animate-spin" alt="loading">
</div>
