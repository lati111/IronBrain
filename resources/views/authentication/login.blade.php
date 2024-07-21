@extends('layouts.form.single_form')

@section('htmlTitle', 'Log In')
@section('form_title', 'Log In')
@section('submit_string', 'Log In')

@section('form_content')
    {{--| email field |--}}
    @component('components.form.input-wrapper')
        @slot('label_text')
            Username
        @endslot
        @slot('input_html')
            <input type="text" name="username" class="largeInput underlined" placeholder="Username"
                   @if(old('username') !== null) value="{{old('username')}}" @endif
                   dusk="username_input" required
            />
        @endslot
    @endcomponent

    {{--| password field |--}}
    @component('components.form.input-wrapper')
        @slot('label_text')
            Password
        @endslot
        @slot('input_html')
            <input type="password" name="password" class="largeInput underlined" placeholder="Password"
                   @if(old('password') !== null) value="{{old('password')}}" @endif
                   dusk="password_input" required
            />
        @endslot
    @endcomponent

    @component('components.form.input.checkbox')
        @slot('name', 'remember_me')
        @slot('right_label', 'Remember Me')
    @endcomponent
@stop

@section('submit_route', route('auth.login.attempt'))
