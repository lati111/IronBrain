@extends('layouts.master')

@section('htmlTitle', 'Deposit')
@section('title', 'Deposit')

@section('css')
    @vite([
        'resources/css/components/datalist/cardlist.css',
        'resources/css/project/pksanc/box.css'
    ])
@stop

@section('script')
    @vite(['resources/ts/modules/pksanc/box.ts'])
@stop

@section('onloadFunction', 'init()')

@section('content')
<div class="flex flex-col justify-center text-center mb-3">
    <p>The following pokemon will be added to your account.</p>
    <p>Is this correct?</p>

    <div class="flex justify-center gap-5">
        <a href="{{route('pksanc.deposit.stage.cancel', $importUuid)}}" class="cancel_interactive">No</a>
        <a href="{{route('pksanc.deposit.stage.confirm', $importUuid)}}" class="interactive">Yes</a>
    </div>

    {{--| cardlist |--}}
    <div class="flex flex-row justify-center mb-3" dusk="form">
        <x-datalist.cardlist.list id="pokemon-cardlist" url="{{route('pksanc.staging.cardlist', $importUuid)}}">
            {{--| template |--}}
            @include('modules.pksanc.snippits.pokemon-cardlist-template')
        </x-datalist.cardlist.list>
    </div>
</div>
@endsection
