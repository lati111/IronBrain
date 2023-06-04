@extends('layouts.form.single_form')

@section('htmlTitle', 'Sign Up')
@section('form_title', 'Create New Account')
@section('submit_string', 'Create Account')

@section('form_content')
    {{--| username  field |--}}
    @component('components.form.input_wrapper')
        @slot('label_text')Username @endslot
        @slot('input_html')
        <input type="text" name="name" class="largeInput underlined" placeholder="Name"
            @if(old('name') !== null) value="{{old('name')}}" @endif
            dusk="name_input" required autocomplete="off"
        />
        @endslot
    @endcomponent

    {{--| email field |--}}
    @component('components.form.input_wrapper')
        @slot('label_text')Email @endslot
        @slot('input_html')
        <input type="email" name="email" class="largeInput underlined" placeholder="Email Adress"
            @if(old('email') !== null) value="{{old('email')}}" @endif
            dusk="email_input" required autocomplete="new-email"
        />
        @endslot
    @endcomponent

    {{--| password field |--}}
    @component('components.form.input_wrapper')
        @slot('label_text')Password @endslot
        @slot('input_html')
        <input type="password" name="password" class="largeInput underlined" placeholder="Password"
            @if(old('password') !== null) value="{{old('password')}}" @endif
            dusk="password_input" required autocomplete="new-password"
        />
        @endslot
    @endcomponent

    {{--| repeat password field |--}}
    @component('components.form.input_wrapper')
        @slot('label_text')Repeat Password @endslot
        @slot('input_html')
        <input type="password" name="repeat_password" class="largeInput underlined" placeholder="Repeat Password"
            @if(old('repeat_password') !== null) value="{{old('repeat_password')}}" @endif
            dusk="repeat_password_input" required
        />
        @endslot
    @endcomponent
@stop

@section('submit_route', route('auth.signup.save'))
