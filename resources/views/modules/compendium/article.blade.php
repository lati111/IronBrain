@extends('layouts.main')

@section('htmlTitle', $article->title)

@section('header')
    @vite([
        'resources/css/components/form/components/image_uploader.css',
        'resources/ts/modules/compendium/article.ts'
    ])
@stop

@section('onload_functions', 'init()')

@section('content')
    <input type="hidden" name="campaign_uuid" value="{{$campaign->uuid}}">
    <input type="hidden" name="article_uuid" value="{{$article->uuid}}">

    <form class="flex flex-col justify-center items-center gap-4">
        {{--| Cover image |--}}
{{--        <div>--}}
{{--            @component('components.form.editable-inputs.toggleable-edit-field')--}}
{{--                @slot('wrapper_text', 'Cover')--}}
{{--                @slot('name', 'cover_src')--}}
{{--                @slot('display')--}}
{{--                    <img src="{{asset('img/modules/compendium/campaign_cover/'.$campaign->cover_src)}}" class="display w-[30rem] h-[18rem]">--}}
{{--                @endslot--}}
{{--                @slot('input')--}}
{{--                    <x-form.image_uploader id="cover-uploader" name="cover_src" cls="w-[30rem] h-[18rem]" src="{{asset('img/modules/compendium/campaign_cover/'.$campaign->cover_src)}}"/>--}}
{{--                @endslot--}}
{{--                @slot('save_method', 'saveImgEdit(this.closest(`.edit-container`), saveCampaignEdits)')--}}
{{--            @endcomponent--}}
{{--        </div>--}}

        {{--| Title |--}}
        <div>
            <x-form.editable-inputs.toggleable-edit-input name="title" value="{{$article->name}}" save_callback="saveArticleInfoEdits" display_cls="title text-4xl" label_text="Title">
                <x-form.input.text_input name="name" value="{{$article->name}}" width="96"/>
            </x-form.editable-inputs.toggleable-edit-input>
        </div>

        {{--| Description |--}}
        <div>
            <x-form.editable-inputs.toggleable-edit-text-area name="description" value="{{$article->description}}" save_callback="saveArticleInfoEdits" display_cls="min-w-[20rem] max-w-[40rem]" label_text="Description">
                <x-form.input.text_area name="description" value="{{$article->description}}" width="96" />
            </x-form.editable-inputs.toggleable-edit-text-area>
        </div>

    </form>
@endsection
