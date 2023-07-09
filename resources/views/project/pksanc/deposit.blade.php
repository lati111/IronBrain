@extends('layouts.form.single_form')

@section('htmlTitle', 'Deposit Pokemon')
@section('form_title', 'Deposit Pokemon')
@section('submit_string', 'Deposit Pokemon')

@section('header')
    @vite(['resources/css/components/form/components/file_uploader.css'])
@stop

@section('form_content')
    {{--| save name field |--}}
    @component('components.form.input_wrapper')
        @slot('label_text')Save file name @endslot
        @slot('input_html')
            <input type="text" name="name" class="largeInput underlined" placeholder="Name"
                @if(old('name') !== null) value="{{old('name')}}" @endif
                dusk="name_input" required
            />
        @endslot
    @endcomponent

    {{--| file upload field |--}}
    @component('components.form.input_wrapper')
        @slot('label_text')Save file csv @endslot
        @slot('input_html')
            @component('components.form.input.file_uploader')
                @slot('name', 'csv')
                @slot('required', true)
                @slot('accepts', '.csv, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel')
            @endcomponent
        @endslot
    @endcomponent

    {{--| game selector |--}}
    @component('components.form.input_wrapper')
        @slot('label_text')Game @endslot
        @slot('input_html')
        <select name="game" class="underlined py-0"/>
            <option>Select a game</option>
            @foreach ($gamesCollection as $game)
                <option value="{{$game->game}}">{{$game->name}}</option>
            @endforeach
        </select>
        @endslot
    @endcomponent
@stop

@section('submit_route', route('pksanc.deposit.stage.attempt'))




