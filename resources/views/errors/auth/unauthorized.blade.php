@extends('layouts.error')

@section('errorTitle', 'Not logged in')

@section('message')
    <p>You must be logged in to access this page</p>
    <p><a href="{{route('auth.login.show')}}" class="interactive">Login</a></p>
    <p>Or</p>
    <p><a href="{{route('home.show')}}" class="interactive">Return Home</a></p>
@stop
