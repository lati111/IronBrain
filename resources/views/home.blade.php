@extends('layouts.master')

@section('htmlTitle', 'Home')
@section('title', 'Home')

@section('header')
    @vite(['resources/css/home.css'])
@endsection

@section('content')
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

    {{--| project cards |--}}
    <div dusk="projects">
        @foreach ($projects as $project)
        <div class="project pb-5">
            <div class="container flex justify-center">
                <div class="w-96">
                    <div class="card shadow-sm">
                        <img src="{{asset("img/project/thumbnail/".$project["thumbnail"])}}" alt="{{$project["name"]}}">
                        <div class="card-body">
                            <h4 class="title text-center">
                                <a href="{{route($project['route'])}}" class="interactive">{{$project["name"]}}</a>
                            </h4>
                            <p class="card-text text-center">{{$project["description"]}}</p>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-body-secondary" title="Last Updated">{{$project["timeAgo"]}}</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
    </div>
@stop
