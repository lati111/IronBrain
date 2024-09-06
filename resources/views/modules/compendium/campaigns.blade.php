@extends('layouts.main')

@section('htmlTitle', 'Compendium Docs')
@section('title', 'Compendium Docs')

@section('header')
    @vite([
        'resources/css/components/datalist/cardlist.css',
        'resources/ts/modules/compendium/campaigns.ts'
])
@stop

@section('onload_functions', 'init()')

@section('content')
    <x-datalist.cardlist.list id="campaign-cardlist" url="{{route('data.compendium.campaigns')}}">
        <x-datalist.cardlist.template dataprovider_id="campaign-cardlist" cls="max-w-md flex flex-col items-center gap-2">
            <img data-name="cover_src" alt="cover image" class="max-w-sm">

            <div class="card-body">
                <h4 class="title text-center">
                    <a data-attribute-name="route" data-settable-attribute="href" class="interactive">
                        <span class="text-xl" data-name="title"></span>
                    </a>
                </h4>

                <p class="card-text text-center">
                    <span data-name="description"></span>
                </p>
            </div>
        </x-datalist.cardlist.template>
    </x-datalist.cardlist.list>
@endsection
