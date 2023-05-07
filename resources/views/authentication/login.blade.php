@extends('layouts.form.single_form')

@section('htmlTitle', 'Log In')
@section('form_title', 'Log In')
@section('submit_string', 'Log In')

@section('form_content')
    {{--| email field |--}}
    @component('components.form.input_wrapper')
        @slot('label_text')Email @endslot
        @slot('input_html')
            <input type="email" name="email" class="largeInput underlined" placeholder="Email Adress"
                @if(old('email') !== null) value="{{old('email')}}" @endif
                required
            />
        @endslot
    @endcomponent

    {{--| password field |--}}
    @component('components.form.input_wrapper')
        @slot('label_text')Password @endslot
        @slot('input_html')
            <input type="password" name="password" class="largeInput underlined" placeholder="Password"
                @if(old('password') !== null) value="{{old('password')}}" @endif
                required
            />
        @endslot
    @endcomponent
@stop

@section('submit_route', route('config.permission.save'))
