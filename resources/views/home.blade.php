@extends('layouts.main')

@section('htmlTitle', 'Home')
@section('title', 'Home')

@section('header')
    @vite([
        'resources/css/home.css',
    ])
@endsection

@section('content')
    {{--| main info |--}}
    <section class="pt-3 pb-5 text-center container">
        <div class="row py-lg-3">
            <div class="col-lg-7 col-md-8 mx-auto">
                <h1 class="title">IronBrain Webtools</h1>
                <p class="lead text-body-secondary">
                    Welcome to IronBrain, a website where we offer a range of hobbyist webtools that have been developed with the intention of enriching various hobbies.
                    <br>
                    Our webtools are chiefly designed with user experience in mind, as we believe that ease of use is important to any proper webtool.
                </p>
            </div>
        </div>
    </section>

    {{--| module cards |--}}
    <div class="flex flex-col items-center gap-8 mb-3" dusk="projects">
        @if(count($overviewModules) > 0)
            <div class="flex justify-center flex-wrap gap-6 max-w-screen-2xl w-full">
                @foreach($overviewModules as $module)
                    <x-modules.card :module="$module"/>
                @endforeach
            </div>
        @endif

        {{--| submodules, grouped under their main module |--}}
        @foreach($overviewGroups as $group)
            <section class="flex flex-col items-center gap-4 max-w-screen-2xl w-full" dusk="group-{{$group['module']->code}}">
                <h2 class="title text-2xl text-center w-full border-b gray-border pb-1">{{$group['module']->name}}</h2>
                <div class="flex justify-center flex-wrap gap-6 w-full">
                    @foreach($group['submodules'] as $submodule)
                        <x-modules.card :module="$submodule"/>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
@stop
