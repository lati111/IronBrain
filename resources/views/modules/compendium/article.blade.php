@extends('layouts.main')

@section('htmlTitle', $article->name)

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
        {{--| Title |--}}
        <div>
            <x-form.editable-inputs.toggleable-edit-input name="title" value="{{$article->name}}" save_callback="saveArticleInfoEdits" display_cls="title text-4xl" label_text="Title">
                <x-form.input.text_input name="name" value="{{$article->name}}" width="96"/>
            </x-form.editable-inputs.toggleable-edit-input>
        </div>

        {{--| Description |--}}
        <div>
            <x-form.editable-inputs.toggleable-edit-text-area name="description" value="{{$article->description}}" save_callback="saveArticleInfoEdits" cls="min-w-[20rem] w-[40rem]" display_cls="max-w-[40rem]" label_text="Description">
                <x-form.input.text_area name="description" value="{{$article->description}}" height="[20rem]" width="full" />
            </x-form.editable-inputs.toggleable-edit-text-area>
        </div>
    </form>

    <x-elements.dividers.horizontal-divider cls="my-4"/>

    {{--| Segment list |--}}
    <div>
        <x-datalist.reorderable-list.list id="article_segment_list"
            url="{{route('data.compendium.campaigns.articles.segments', ['campaign_uuid' => $campaign->uuid, 'article_uuid' => $article->uuid])}}"
        />

        <div class="hidden">
            <x-datalist.reorderable-list.template id="article_segment_list">
                <x-form.editable-inputs.toggleable-edit-text-area name="content" save_callback="saveSegmentContent" cls="min-w-[20rem] w-[40rem]" label_name="title" label_text="a">
                    <x-form.input.text_area name="content" data-name="content" height="[20rem]" width="full" />
                </x-form.editable-inputs.toggleable-edit-text-area>
            </x-datalist.reorderable-list.template>
        </div>
    </div>

@endsection
