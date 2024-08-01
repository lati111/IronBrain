@extends('layouts.form.single_form')

@section('htmlTitle', 'Log In')
@section('form_title', 'Log In')

@section('header')
    @vite(['resources/ts/auth/login.ts'])
@endsection

@section('form_content')
    {{--| username field |--}}
    <x-form.input-wrapper label_text="Username">
        <input type="text" name="username" class="large_input underlined" placeholder="Username" required/>
    </x-form.input-wrapper>

    {{--| password field |--}}
    <x-form.input-wrapper label_text="Password">
        <input type="password" name="password" class="largeInput underlined" placeholder="Password" required/>
    </x-form.input-wrapper>

    {{--| remember me field |--}}
    <x-form.input.checkbox name="remember_me" right_label="Remember Me"/>
@stop

@section('submit_text', 'Log In')
@section('submit_method', 'attemptLogin()')
