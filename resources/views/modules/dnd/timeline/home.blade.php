@extends('layouts.main')

@section('htmlTitle', 'Fantasy Timeline')
@section('title', 'Fantasy Timeline')

@section('header')
    @vite([
        'resources/css/components/datalist/cardlist.css',
        'resources/ts/modules/dnd/timeline/home.ts',
    ])
@stop

@section('onload_functions', 'init()')

@section('content')
    <section class="pt-3 pb-5 text-center container">
        <h1 class="title">Fantasy Timeline</h1>
    </section>

    {{--| Cardlist topbar |--}}
    <div class="flex justify-around items-center mb-4">
        <span class="w-64"></span>

        <x-datalist.parts.searchbar id="timeline-cardlist"></x-datalist.parts.searchbar>

        <button class="interactive w-64" onclick="openCreateTimelineModal()" dusk="timeline-create">+ New Timeline</button>
    </div>

    {{--| Timeline cardlist |--}}
    <div class="flex flex-row justify-center mb-3">
        <x-datalist.cardlist.list id="timeline-cardlist" url="{{route('data.dnd.timeline.overview')}}" :include-searchbar="false">
            @include('modules.dnd.timeline.snippits.timeline-cardlist-template')
        </x-datalist.cardlist.list>
    </div>

    {{--| Create timeline modal |--}}
    <x-modal id="timeline-create-modal">
        <h3 class="title py-1">New Timeline</h3>

        <form id="timeline-create-form" class="flex flex-col gap-3 w-full" onsubmit="event.preventDefault(); createTimeline()">
            <x-form.input-wrapper label_text="Name">
                <input type="text" id="timeline-create-name" name="name" class="underlined" maxlength="64"
                       placeholder="Name..." required/>
            </x-form.input-wrapper>
            <x-form.input-wrapper label_text="Description (optional)">
                <textarea name="description" class="underlined" rows="4" maxlength="2048" placeholder="Description..."></textarea>
            </x-form.input-wrapper>
            <x-form.input-wrapper label_text="Cover image (optional)">
                <x-form.input.file_uploader name="cover_image" accepts="image/*"/>
            </x-form.input-wrapper>
            <button type="submit" class="hidden"></button>
        </form>

        <x-slot:buttons>
            <button class="cancel_interactive px-5 py-2.5"
                    onclick="closeModal('timeline-create-modal')">Cancel</button>
            <button class="interactive px-5 py-2.5" dusk="confirm"
                    onclick="createTimeline()">Create</button>
        </x-slot:buttons>
    </x-modal>
@endsection
