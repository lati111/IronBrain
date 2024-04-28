@extends('layouts.main')

@section('htmlTitle', 'Home')
@section('title', 'Home')

@section('header')
    @vite([
        'resources/css/home.css',
        'resources/ts/home.ts'
    ])
@endsection

@section('onload_functions', 'init()')

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

    {{--| project cards |--}}
    <div dusk="projects">
        <div class="flex flex-row justify-center mb-3" dusk="form">
            <x-datalist.cardlist.list id="module-cardlist" url="{{route('home.overview.cardlist')}}">
                {{--| template |--}}
                <x-datalist.cardlist.template id="module-cardlist" class="max-w-md flex flex-col items-center gap-2">
                    <img data-name="thumbnail" data-alt-name="name" class="max-w-sm">

                    <div class="card-body">
                        <h4 class="title text-center">
                            <a data-attribute-name="route" data-settable-attribute="href" class="interactive">
                                <span data-name="name"></span>
                            </a>
                        </h4>

                        <p class="card-text text-center">
                            <span data-name="description"></span>
                        </p>

                        <div class="flex justify-content-between align-items-center">
                            <small class="text-body-secondary" title="Last Updated">
                                <span data-name="time_ago"></span>
                            </small>
                        </div>
                    </div>
                </x-datalist.cardlist.template>
            </x-datalist.cardlist.list>
        </div>
    </div>
@stop
