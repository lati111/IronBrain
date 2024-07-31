@extends('layouts.form.single_form')

@section('htmlTitle', 'Sign Up')
@section('form_title', 'Create New Account')

@section('form_content')
    {{--| username  field |--}}
    <x-form.input-wrapper label_text="Username">
        <input type="text" name="username" class="large_input underlined" placeholder="Username" autocomplete="new-username" required/>
    </x-form.input-wrapper>

    {{--| email field |--}}
    <x-form.input-wrapper label_text="Email (optional)">
        <input type="email" name="email" class="largeInput underlined" placeholder="Email Adress" autocomplete="new-email"/>
    </x-form.input-wrapper>

    {{--| password field |--}}
    <x-form.input-wrapper label_text="Password">
        <input type="password" name="password" class="largeInput underlined" placeholder="Password" required autocomplete="new-password"/>
    </x-form.input-wrapper>

    {{--| repeat password field |--}}
    <x-form.input-wrapper label_text="Repeat Password">
        <input type="password" name="repeat_password" class="largeInput underlined" placeholder="Repeat Password" required/>
    </x-form.input-wrapper>
@stop

@section('submit_text', 'Create Account')
@section('submit_method', 'attemptSignup()')

@section('header')
    @vite(['resources/ts/auth/signup.ts'])
@endsection
