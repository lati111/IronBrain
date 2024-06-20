@extends('layouts.form.single_form')

@section('htmlTitle', 'Log In')
@section('form_title', 'Log In')
@section('submit_string', 'Log In')

@section('form_content')
    {{--| email field |--}}
    @component('components.form.input-wrapper')
        @slot('label_text')
            Email
        @endslot
        @slot('input_html')
            <input type="email" name="email" class="largeInput underlined" placeholder="Email Adress"
                   @if(old('email') !== null) value="{{old('email')}}" @endif
                   dusk="email_input" required
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
