@extends('layouts.main')

@section('htmlTitle', $timeline->name)
@section('title', $timeline->name)

@section('content')
    <section class="pt-3 pb-5 text-center container flex flex-col items-center gap-3">
        @if($timeline->cover_image !== null)
            <img src="{{$timeline->getCoverUrl()}}" alt="{{$timeline->name}}" class="max-h-64 rounded object-cover">
        @endif

        <h1 class="title">{{$timeline->name}}</h1>

        @if($timeline->description !== null)
            <p class="lead text-body-secondary whitespace-pre-line">{{$timeline->description}}</p>
        @endif

        <a href="{{route('dnd.timeline.overview')}}" class="interactive">Back to timelines</a>
    </section>
@endsection
