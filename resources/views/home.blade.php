@extends('layouts.master')

@section('htmlTitle', 'Home')
@section('title', 'Home')

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

    <div class="album pb-5">
        <div class="container flex justify-center">
            <div class="w-96">
                <div class="card shadow-sm">
                    <svg class="bd-placeholder-img card-img-top" width="100%" height="225" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Placeholder: Thumbnail" preserveAspectRatio="xMidYMid slice" focusable="false"><title>Placeholder</title><rect width="100%" height="100%" fill="#55595c"/><text x="50%" y="50%" fill="#eceeef" dy=".3em">Thumbnail</text></svg>
                    <div class="card-body">
                    <p class="card-text">This is a wider card with supporting text below as a natural lead-in to additional content. This content is a little bit longer.</p>
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-body-secondary" title="Last Updated">9 mins</small>
                    </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop
