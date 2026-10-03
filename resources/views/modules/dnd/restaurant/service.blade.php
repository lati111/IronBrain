@extends('layouts.main')

@section('htmlTitle', $restaurant->name)
@section('title', $restaurant->name)

@section('content')
    {{--| Top bar |--}}
    <div class="flex justify-between items-center mb-4">
        <a href="{{route('dnd.restaurant.management', ['restaurant_uuid' => $restaurant->uuid])}}" class="interactive w-64">&larr; Management</a>
        <h1 class="title">{{$restaurant->name}} &mdash; Service</h1>
        <span class="w-64"></span>
    </div>

    {{--| Service clock |--}}
    <section class="flex flex-col items-center gap-1 py-6">
        <span class="text-gray-500">{{$restaurant->date->format('j F Y')}}</span>
        <span class="text-4xl font-semibold" title="Current time">{{substr($restaurant->current_time, 0, 5)}}</span>
        <small class="text-gray-500">
            Open {{substr($restaurant->opening_time, 0, 5)}} &ndash; {{substr($restaurant->closing_time, 0, 5)}}
        </small>
    </section>
@endsection
