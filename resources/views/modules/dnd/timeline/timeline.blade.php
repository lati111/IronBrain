@extends('layouts.main')

@section('htmlTitle', $timeline->name)
@section('title', $timeline->name)

@section('header')
    @vite([
        'resources/css/modules/dnd/timeline/timeline.css',
        'resources/ts/modules/dnd/timeline/timeline.ts',
    ])
@stop

@section('onload_functions', 'init()')

@section('content')
    {{--| Top bar. Both sides share a width, so the title stays centred |--}}
    <div class="flex justify-between items-center gap-4 mb-3 timeline-page">
        <div class="flex-1 flex gap-4 whitespace-nowrap">
            <a href="{{route('dnd.timeline.overview')}}" class="interactive">&larr; Timelines</a>
        </div>

        <h1 class="title">{{$timeline->name}}</h1>

        <div class="flex-1 flex justify-end items-center gap-2 whitespace-nowrap">
            <button class="interactive px-3" onclick="zoomTimeline(1 / 1.5)" title="Zoom out" dusk="timeline-zoom-out">−</button>
            <button class="interactive px-3" onclick="zoomTimeline(1.5)" title="Zoom in" dusk="timeline-zoom-in">+</button>
            <button class="interactive px-3" onclick="openSettingsModal()" dusk="timeline-settings">Settings</button>
            <button class="interactive px-3" onclick="openCategoryModal()" dusk="timeline-categories">Categories</button>
            <button class="interactive px-3" onclick="openItemModal()" dusk="timeline-item-create">+ Add to timeline</button>
        </div>
    </div>

    {{--| Legend |--}}
    <div id="timeline-legend" class="flex flex-wrap justify-center items-center gap-3 mb-3 text-sm timeline-page"></div>

    {{--| Timeline |--}}
    <div id="timeline" class="timeline-page" data-api-url="{{url('/api/dnd/timeline/'.$timeline->uuid)}}">
        <div id="timeline-scroll" class="timeline-scroll">
            <div id="timeline-track" class="timeline-track"></div>
        </div>
        <p id="timeline-empty" class="hidden text-center text-body-secondary py-12">
            Nothing has happened in this world yet. Add an event, timespan or period to start its history.
        </p>
    </div>

    {{--| Item modal |--}}
    <x-modal id="timeline-item-modal">
        <h3 id="timeline-item-title" class="title py-1">Add to timeline</h3>

        <form id="timeline-item-form" class="flex flex-col gap-3 w-full" onsubmit="event.preventDefault(); saveItem()">
            <x-form.input-wrapper label_text="Type">
                <select name="type" id="timeline-item-type" class="underlined" onchange="updateItemTypeFields()">
                    <option value="event">Event: takes place on a single year</option>
                    <option value="timespan">Timespan: takes place from one year to another</option>
                    <option value="period">Period: a wider period of time, such as an age or era</option>
                </select>
            </x-form.input-wrapper>

            <x-form.input-wrapper label_text="Name">
                <input type="text" id="timeline-item-name" name="name" class="underlined" maxlength="128"
                       placeholder="Name..." required/>
            </x-form.input-wrapper>

            {{--| Years, of which only those of the selected type are enabled |--}}
            <div class="flex gap-3">
                <div class="flex-1" data-item-years="single">
                    <x-form.input-wrapper label_text="Year">
                        <input type="number" name="year" class="underlined" step="1" required/>
                    </x-form.input-wrapper>
                </div>
                <div class="flex-1 hidden" data-item-years="range">
                    <x-form.input-wrapper label_text="Start year">
                        <input type="number" name="start_year" class="underlined" step="1" required disabled/>
                    </x-form.input-wrapper>
                </div>
                <div class="flex-1 hidden" data-item-years="range">
                    <x-form.input-wrapper label_text="End year">
                        <input type="number" name="end_year" class="underlined" step="1" required disabled/>
                    </x-form.input-wrapper>
                </div>
            </div>

            {{--| Category, which is created along with the item when it doesn't exist yet |--}}
            <x-form.input-wrapper label_text="Category">
                <div class="flex gap-2 items-center">
                    <input type="text" name="category" id="timeline-item-category" list="timeline-category-options"
                           class="underlined flex-1" maxlength="64" placeholder="Category..." autocomplete="off"
                           oninput="updateCategoryColor()" required/>
                    <datalist id="timeline-category-options"></datalist>
                    <input type="color" name="category_color" id="timeline-category-color" value="#3b82f6"
                           class="h-9 w-12 hidden" title="The color of the new category"/>
                </div>
            </x-form.input-wrapper>

            <x-form.input-wrapper label_text="Description (optional)">
                <textarea name="description" class="underlined" rows="4" maxlength="4096" placeholder="Description..."></textarea>
            </x-form.input-wrapper>

            <x-form.input-wrapper label_text="Tags (optional, comma separated)">
                <input type="text" name="tags" class="underlined" maxlength="1024" placeholder="Tags..."/>
            </x-form.input-wrapper>

            <button type="submit" class="hidden"></button>
        </form>

        <x-slot:buttons>
            <button class="cancel_interactive px-5 py-2.5"
                    onclick="closeModal('timeline-item-modal')">Cancel</button>
            <button id="timeline-item-delete" class="cancel_interactive px-5 py-2.5 hidden" dusk="delete"
                    onclick="deleteItem()">Delete</button>
            <button class="interactive px-5 py-2.5" dusk="confirm"
                    onclick="saveItem()">Save</button>
        </x-slot:buttons>
    </x-modal>

    {{--| Categories modal |--}}
    <x-modal id="timeline-category-modal">
        <h3 class="title py-1">Categories</h3>
        <div id="timeline-category-list" class="flex flex-col gap-3 w-full max-h-96 overflow-y-auto"></div>
        <x-slot:buttons>
            <button class="cancel_interactive px-5 py-2.5" onclick="closeModal('timeline-category-modal')">Close</button>
        </x-slot:buttons>
    </x-modal>

    {{--| Settings modal, of which every setting may be left empty |--}}
    <x-modal id="timeline-settings-modal">
        <h3 class="title py-1">Settings</h3>

        <form id="timeline-settings-form" class="flex flex-col gap-3 w-full" onsubmit="event.preventDefault(); saveSettings()">
            <x-form.input-wrapper label_text="Current year (optional)">
                <input type="number" name="current_year" class="underlined" step="1" placeholder="Year..."/>
            </x-form.input-wrapper>

            {{--| The years shown when the timeline is opened, where an empty end runs to the edge of the timeline |--}}
            <div class="flex gap-3">
                <x-form.input-wrapper label_text="Display from (optional)" cls="flex-1">
                    <input type="number" name="display_start_year" class="underlined" step="1" placeholder="Year..."/>
                </x-form.input-wrapper>
                <x-form.input-wrapper label_text="Display to (optional)" cls="flex-1">
                    <input type="number" name="display_end_year" class="underlined" step="1" placeholder="Year..."/>
                </x-form.input-wrapper>
            </div>

            <button type="submit" class="hidden"></button>
        </form>

        <x-slot:buttons>
            <button class="cancel_interactive px-5 py-2.5" onclick="closeModal('timeline-settings-modal')">Cancel</button>
            <button class="interactive px-5 py-2.5" dusk="settings-save" onclick="saveSettings()">Save</button>
        </x-slot:buttons>
    </x-modal>
@endsection
