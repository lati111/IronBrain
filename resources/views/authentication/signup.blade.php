@extends('layouts.form.single_form')

@section('htmlTitle', 'Sign Up')
@section('form_title', 'Create New Account')
@section('submit_string', 'Create Account')

@section('form_content')
    {{--| username  field |--}}
    @component('components.form.input-wrapper')
        @slot('label_text')
            Username
        @endslot
        @slot('input_html')
            <input type="text" name="username" class="largeInput underlined" placeholder="Username"
                   @if(old('username') !== null) value="{{old('username')}}" @endif
                   dusk="username_input" autocomplete="new-username" required
            />
        @endslot
    @endcomponent

    {{--| email field |--}}
    @component('components.form.input-wrapper')
        @slot('label_text')
            Email (optional)
        @endslot
        @slot('input_html')
            <input type="email" name="email" class="largeInput underlined" placeholder="Email Adress"
                   @if(old('email') !== null) value="{{old('email')}}" @endif
                   dusk="email_input" autocomplete="new-email"
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
                   dusk="password_input" required autocomplete="new-password"
            />
        @endslot
    @endcomponent

    {{--| repeat password field |--}}
    @component('components.form.input-wrapper')
        @slot('label_text')
            Repeat Password
        @endslot
        @slot('input_html')
            <input type="password" name="repeat_password" class="largeInput underlined" placeholder="Repeat Password"
                   @if(old('repeat_password') !== null) value="{{old('repeat_password')}}" @endif
                   dusk="repeat_password_input" required
            />
        @endslot
    @endcomponent
@stop

@section('submit_route', route('auth.signup.save'))
