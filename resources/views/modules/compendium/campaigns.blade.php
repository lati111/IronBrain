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
    {{--| Topbar |--}}
    <div class="flex justify-end w-full">
        <x-elements.buttons.button onclick="openNewCampaignModal()">Start a new campaign</x-elements.buttons.button>
    </div>

    {{--| Campaign cardlist |--}}
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

    {{--| New campaign modal |--}}
    <x-modal.modal id="new-campaign-modal" confirm_text="Create" confirm_method="submitNewCampaignModal()">
        <form id="new-campaign-form" class="flex flex-col justify-center gap-3">
            <h4 class="title text-center">Start a campaign</h4>

            <x-form.input-wrapper label_text="Title" cls="w-64">
                <input name="title" class="underlined px-1 w-full">
            </x-form.input-wrapper>
        </form>
    </x-modal.modal>
@endsection
