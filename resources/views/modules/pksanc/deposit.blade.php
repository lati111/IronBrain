@extends('layouts.form.single_form')

@section('htmlTitle', 'Deposit Pokemon')
@section('form_title', 'Deposit Pokemon')
@section('submit_string', 'Deposit Pokemon')

@section('header')
    @vite([
        'resources/css/components/form/components/file_uploader.css',
        'resources/ts/components/modal.ts',
        'resources/ts/modules/pksanc/deposit.ts',
    ])
@stop

@section('onloadFunction', 'init()')

@section('form_content')
    {{--| info blurb |--}}
    <div class="text-center">
        <div>Import a set of pokemon from a save file into PKSanc.</div>
        <p>
            Save files must be uploaded in CSV form generated by
            <a href="https://github.com/lati111/PKSaveExtract" class="interactive">PKSaveExtract</a> in PKSanc format
        </p>
    </div>


    <div class="flex flex-col justify-center items-center gap-3">
        {{--| save name field |--}}
        @component('components.form.input-wrapper')
            @slot('label_text')
                Save file name
            @endslot
            @slot('classes', 'largeInput')
            @slot('input_html')
                <input type="text" name="name" class="defaultInputHeight underlined" placeholder="Name"
                       @if(old('name') !== null) value="{{old('name')}}" @endif
                       dusk="name_input" required
                />
            @endslot
        @endcomponent


        {{--| file upload field |--}}
        @component('components.form.input-wrapper')
            @slot('label_text')
                Save file csv
            @endslot
            @slot('classes', 'largeInput')
            @slot('input_html')
                @component('components.form.input.file_uploader')
                    @slot('name', 'csv')
                    @slot('required', true)
                    @slot('accepts', '.csv, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel')
                @endcomponent
            @endslot
        @endcomponent

        {{--| game selector |--}}
        @component('components.form.input-wrapper')
            @slot('label_text')
                Game
            @endslot
            @slot('classes', 'largeInput')
            @slot('input_html')
                <div class="flex gap-1 h-8">
                    <x-datalist.dataselect.dataselect id="game-selector" name="game" url="{{route('pksanc.games.dataselect')}}" identifier="game" label="name" dynamicloading="false">
                    </x-datalist.dataselect.dataselect>

                    <button type="button" class="interactive font-bold text-2xl" title="add game" onclick="openModal('add_romhack_modal')">+</button>
                </div>
            @endslot
        @endcomponent
    </div>
@stop

@section('modals')
    @component('components.modal.modal')
        @slot('id', 'add_romhack_modal')
        @slot('body')
            <h3 class="title py-1">Add a romhack</h3>

            @component('components.form.input-wrapper')
                @slot('label_text')
                    Romhack name
                @endslot
                @slot('input_html')
                    <input type="text" name="romhack_name" class="mediumInput underlined h-8" placeholder="Name" required>
                @endslot
            @endcomponent

            @component('components.form.input-wrapper')
                @slot('label_text')
                    Original game
                @endslot
                @slot('input_html')
                    <select name="romhack_original_game" class="mediumInput underlined py-0">
                        <option>Original game</option>
                        @foreach ($gamesCollection as $game)
                            @if ($game->original_game === null)
                                <option value="{{$game->game}}">{{$game->name}}</option>
                            @endif
                        @endforeach
                    </select>
                @endslot
            @endcomponent
        @endslot
        @slot('buttons')
            <button type="button" onclick="closeModal('add_romhack_modal')" class="cancel_interactive px-5 py-2.5 text-center">Cancel
            </button>
            <button type="button" onclick="saveRomhack();"
                    class="interactive px-5 py-2.5 text-center" dusk="confirm">Submit
            </button>
        @endslot
    @endcomponent
@stop

@section('submit_route', route('pksanc.deposit.stage.attempt'))




