@extends('layouts.error')

@section('errorTitle', 'No access')

@section('message')
    <p>Your account does not have the right permissions for this page</p>
    <p><a href="{{route('home.show')}}" class="interactive">Return Home</a></p>
@stop
