@extends('layouts.main')

@section('htmlTitle', $campaign->title)

@section('header')
    @vite([
        'resources/css/components/form/components/image_uploader.css',
        'resources/ts/modules/compendium/campaign.ts'
    ])
@stop

@section('onload_functions', 'init()')

@section('content')
    <input type="hidden" name="campaign_uuid" value="{{$campaign->uuid}}">

    <form class="flex flex-col justify-center items-center gap-4">
        {{--| Cover image |--}}
        <div>
            @component('components.form.editable-inputs.toggleable-edit-field')
                @slot('wrapper_text', 'Cover')
                @slot('name', 'cover_src')
                @slot('display')
                    <img src="{{asset('img/modules/compendium/campaign_cover/'.$campaign->cover_src)}}" class="display w-[30rem] h-[18rem]">
                @endslot
                @slot('input')
                    <x-form.image_uploader id="cover-uploader" name="cover_src" cls="w-[30rem] h-[18rem]" src="{{asset('img/modules/compendium/campaign_cover/'.$campaign->cover_src)}}"/>
                @endslot
                @slot('save_method', 'saveImgEdit(this.closest(`.edit-container`), saveCampaignEdits)')
            @endcomponent
        </div>

        {{--| Title |--}}
        <div>
            <x-form.editable-inputs.toggleable-edit-input name="title" value="{{$campaign->title}}" save_callback="saveCampaignEdits" display_cls="title text-4xl" label_text="Title">
                <x-form.input.text_input name="title" value="{{$campaign->title}}" width="96"/>
            </x-form.editable-inputs.toggleable-edit-input>
        </div>

        {{--| Description |--}}
        <div>
            <x-form.editable-inputs.toggleable-edit-text-area name="description" value="{{$campaign->description}}" save_callback="saveCampaignEdits" display_cls="min-w-[20rem]" label_text="Description">
                <x-form.input.text_area name="description" value="{{$campaign->description}}" width="96" />
            </x-form.editable-inputs.toggleable-edit-text-area>
        </div>

        {{--| Articles |--}}
        <h3 class="title text-center mt-12">Articles</h3>
        <x-datalist.cardlist.list id="article-cardlist" url="{{route('data.compendium.campaigns.articles', ['campaign_uuid' => $campaign->uuid])}}">
            <x-datalist.cardlist.template dataprovider_id="article-cardlist" cls="max-w-xs flex flex-col items-center gap-2">
                <div class="card-body">
                    <h4 class="title text-center">
                        <a data-attribute-name="article_url" data-settable-attribute="href" class="interactive">
                            <span class="text-xl" data-name="name"></span>
                        </a>
                    </h4>

                    <p id="description-container" class="card-text text-center">
                        <x-elements.display.shortened-description/>
                    </p>

                    <div id="tags" class="flex justify-center gap-2 mt-1"></div>

                </div>
            </x-datalist.cardlist.template>
        </x-datalist.cardlist.list>
    </form>
@endsection
