@extends('layouts.main')

@section('htmlTitle', 'Deposit')
@section('title', 'Deposit')

@section('header')
    @vite([
        'resources/css/components/datalist/cardlist.css',
        'resources/css/modules/pksanc/box.css',
        'resources/ts/modules/pksanc/staging-overview.ts'
    ])
@stop

@section('onload_functions', 'init()')

@section('content')
<div class="flex flex-col justify-center text-center mb-3">
    <p>
        The following pokemon will be added to your account.
        <br>
        You can exclude pokemon by clicking the import icon between cards.
    </p>
    <p>Are these pokemon correct?</p>

    <div class="flex justify-center gap-5">
        <a href="{{route('pksanc.deposit.stage.cancel', $importUuid)}}" class="cancel_interactive">No</a>
        <button onclick="confirmDeposit('{{$importUuid}}')" class="interactive">Yes</button>
    </div>

    {{--| cardlist |--}}
    <div class="flex flex-row justify-center mb-3" dusk="form">
        <x-datalist.cardlist.list id="pokemon-cardlist" url="{{route('data.pksanc.staging', $importUuid)}}">
            {{--| template |--}}
            @include('modules.pksanc.snippits.pokemon-cardlist-template', ['staging' => true])
        </x-datalist.cardlist.list>
    </div>
</div>
@endsection
